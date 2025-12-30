<?php declare(strict_types=1);

/**
 * REST API for Graph library - Slim Framework Entry Point
 *
 * Authentication Modes:
 * 1. Anonymous - GET requests can be made without authentication
 * 2. Basic Auth - Username/password authentication
 * 3. Bearer Token - Token-based authentication for automations
 *
 * State-changing operations (POST, PUT, DELETE, PATCH) REQUIRE authentication.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Internet\Graph\Graph;
use Internet\Graph\Authenticator;
use Internet\Graph\Config;
use Internet\Graph\SessionManager;
use Internet\Graph\AuditContext;
use Internet\Graph\Container\ContainerFactory;
use Internet\Graph\Action\Graph\GetGraphAction;
use Internet\Graph\Action\Node\CreateNodeAction;
use Internet\Graph\Action\Node\GetNodeAction;
use Internet\Graph\Action\Node\UpdateNodeAction;
use Internet\Graph\Action\Node\DeleteNodeAction;
use Internet\Graph\Action\Node\GetNodeStatusAction;
use Internet\Graph\Action\Node\SetNodeStatusAction;
use Internet\Graph\Action\Node\GetNodeStatusHistoryAction;
use Internet\Graph\Action\Edge\CreateEdgeAction;
use Internet\Graph\Action\Edge\GetEdgeAction;
use Internet\Graph\Action\Edge\DeleteEdgeAction;
use Internet\Graph\Action\Edge\DeleteEdgesFromAction;
use Internet\Graph\Action\Auth\LoginAction;
use Internet\Graph\Action\Auth\LogoutAction;
use Internet\Graph\Action\Auth\GetAuthStatusAction;
use Internet\Graph\Action\Auth\GetCsrfTokenAction;
use Internet\Graph\Action\Backup\CreateBackupAction;
use Internet\Graph\Action\Audit\GetAuditHistoryAction;
use Internet\Graph\Action\Restore\RestoreEntityAction;
use Internet\Graph\Action\Restore\RestoreToTimestampAction;
use Internet\Graph\Action\Status\GetAllNodeStatusesAction;
use Internet\Graph\Action\Status\GetAllowedStatusesAction;
use Internet\Graph\Middleware\ExceptionHandlerMiddleware;
use Internet\Graph\Middleware\ResponseTransformerMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// Load configuration
Config::load();
SessionManager::start();

// Database setup
$db_path = Config::get('DB_PATH');
if (!str_starts_with($db_path, '/')) {
    $db_file = realpath(__DIR__) . '/' . $db_path;
    $db_file = str_replace('\\', '/', $db_file);
    $parts = explode('/', $db_file);
    $resolved = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') {
            array_pop($resolved);
        } else {
            $resolved[] = $part;
        }
    }
    $db_file = '/' . implode('/', $resolved);
} else {
    $db_file = $db_path;
}

// Create DI container
$container = ContainerFactory::create($db_file);

// Create Slim app with container
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware(); // Parse JSON request bodies

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Get dependencies from container
$authenticator = $container->get(Authenticator::class);

// ============================================================================
// MIDDLEWARE
// ============================================================================

// CORS Middleware
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', Config::get('CORS_ALLOWED_ORIGINS', '*'))
        ->withHeader('Access-Control-Allow-Methods', Config::get('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS'))
        ->withHeader('Access-Control-Allow-Headers', Config::get('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-CSRF-Token'));
});

// OPTIONS preflight handler
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

// Authentication Middleware
$authMiddleware = function (Request $request, $handler) use ($authenticator) {
    // Determine user ID
    $user_id = null;
    if (SessionManager::isAuthenticated()) {
        $user_id = SessionManager::getUser();
    } else {
        $user_id = $authenticator->authenticate();
    }

    // Allow anonymous for GET, require auth for state-changing operations
    $method = $request->getMethod();
    $path = $request->getUri()->getPath();
    $isReadOnly = ($method === 'GET');
    $isLoginRoute = ($path === '/api.php/auth/login');

    if (!$isReadOnly && !$isLoginRoute && ($user_id === null || $user_id === 'anonymous')) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'error' => 'Authentication required',
            'message' => 'Authentication is required for ' . $method . ' requests'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    if ($user_id === null) {
        $user_id = 'anonymous';
    }

    // Initialize audit context
    $ip_address = $request->getHeader('X-Forwarded-For')[0] ??
                  $request->getHeader('X-Real-IP')[0] ??
                  $request->getServerParams()['REMOTE_ADDR'] ?? null;
    AuditContext::set($user_id, $ip_address);

    // Store user_id in request attributes for handlers
    $request = $request->withAttribute('user_id', $user_id);

    return $handler->handle($request);
};

$app->add($authMiddleware);

// CSRF Middleware
$csrfMiddleware = function (Request $request, $handler) {
    $isSessionAuth = SessionManager::isAuthenticated();
    $isStateChanging = in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH']);
    $path = $request->getUri()->getPath();
    $isLoginRoute = ($path === '/api.php/auth/login');

    if ($isSessionAuth && $isStateChanging && !$isLoginRoute) {
        $token = $request->getHeader('X-CSRF-Token')[0] ??
                 $request->getParsedBody()['csrf_token'] ?? null;

        if (!SessionManager::validateCsrfToken($token ?? '')) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'CSRF token validation failed',
                'message' => 'Please include a valid CSRF token'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
    }

    return $handler->handle($request);
};

$app->add($csrfMiddleware);

// Response Transformer Middleware - wraps successful responses with metadata
$app->add(new ResponseTransformerMiddleware());

// Exception Handler Middleware - catches exceptions and formats error responses
$app->add(new ExceptionHandlerMiddleware());

// ============================================================================
// ROUTES
// ============================================================================

// Authentication routes - NEW PATTERN (Action classes)
$app->group('/api.php/auth', function (RouteCollectorProxy $group) {
    $group->post('/login', LoginAction::class);
    $group->post('/logout', LogoutAction::class);
    $group->get('/status', GetAuthStatusAction::class);
    $group->get('/csrf', GetCsrfTokenAction::class);
});

// Graph routes
$app->get('/api.php', GetGraphAction::class);
$app->get('/api.php/graph', GetGraphAction::class);

// Node routes - NEW PATTERN (Action classes with DI)
$app->group('/api.php/nodes', function (RouteCollectorProxy $group) {
    // Create node
    $group->post('', CreateNodeAction::class);

    // Get node (check existence)
    $group->get('/{id}', GetNodeAction::class);

    // Update node
    $group->put('/{id}', UpdateNodeAction::class);

    // Delete node
    $group->delete('/{id}', DeleteNodeAction::class);

    // Node status routes
    $group->get('/{id}/status/history', GetNodeStatusHistoryAction::class);
    $group->get('/{id}/status', GetNodeStatusAction::class);
    $group->post('/{id}/status', SetNodeStatusAction::class);
});

// Edge routes - NEW PATTERN (Action classes)
$app->group('/api.php/edges', function (RouteCollectorProxy $group) {
    $group->post('', CreateEdgeAction::class);
    $group->get('/{id}', GetEdgeAction::class);
    $group->delete('/from/{source}', DeleteEdgesFromAction::class);
    $group->delete('/{id}', DeleteEdgeAction::class);
});

// Backup routes - NEW PATTERN (Action classes)
$app->post('/api.php/backup', CreateBackupAction::class);

// Audit routes - NEW PATTERN (Action classes)
$app->get('/api.php/audit', GetAuditHistoryAction::class);

// Restore routes - NEW PATTERN (Action classes)
$app->group('/api.php/restore', function (RouteCollectorProxy $group) {
    $group->post('/entity', RestoreEntityAction::class);
    $group->post('/timestamp', RestoreToTimestampAction::class);
});

// Status routes - NEW PATTERN (Action classes)
$app->get('/api.php/status', GetAllNodeStatusesAction::class);
$app->get('/api.php/status/allowed', GetAllowedStatusesAction::class);

// Run app
$app->run();
