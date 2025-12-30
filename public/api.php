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
use Internet\Graph\ApiHandler;
use Internet\Graph\Authenticator;
use Internet\Graph\Config;
use Internet\Graph\SessionManager;
use Internet\Graph\AuditContext;

require __DIR__ . '/../vendor/autoload.php';

// Load configuration
Config::load();
SessionManager::start();

// Create Slim app
$app = AppFactory::create();
$app->addRoutingMiddleware();

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Initialize dependencies
$valid_bearer_tokens = Config::getAuthBearerTokens();
$valid_users = Config::getAuthUsers();
$authenticator = new Authenticator($valid_bearer_tokens, $valid_users);

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

$graph = new Graph($db_file);
$api = new ApiHandler($graph);

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

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function jsonResponse(Response $response, $data, int $status = 200): Response {
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}

function handleApiResult(Response $response, array $result): Response {
    if (isset($result['success']) && $result['success']) {
        $data = ['success' => true, 'message' => $result['message'] ?? 'Success'];
        if (isset($result['data'])) {
            $data['data'] = $result['data'];
        }
        return jsonResponse($response, $data);
    } else {
        $data = ['error' => $result['error'] ?? 'Operation failed'];
        if (isset($result['details'])) {
            $data['details'] = $result['details'];
        }
        return jsonResponse($response, $data, $result['code'] ?? 500);
    }
}

// ============================================================================
// ROUTES
// ============================================================================

// Authentication routes
$app->group('/api.php/auth', function (RouteCollectorProxy $group) use ($valid_users) {
    $group->post('/login', function (Request $request, Response $response) use ($valid_users) {
        $body = $request->getParsedBody();
        $username = $body['username'] ?? null;
        $password = $body['password'] ?? null;

        if (!$username || !$password) {
            return jsonResponse($response, ['error' => 'Missing username or password'], 400);
        }

        if (isset($valid_users[$username]) && password_verify($password, $valid_users[$username])) {
            SessionManager::setUser($username);
            return jsonResponse($response, [
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $username,
                    'csrf_token' => SessionManager::getCsrfToken()
                ]
            ]);
        }

        return jsonResponse($response, ['error' => 'Invalid credentials'], 401);
    });

    $group->post('/logout', function (Request $request, Response $response) {
        SessionManager::destroy();
        return jsonResponse($response, ['success' => true, 'message' => 'Logout successful']);
    });

    $group->get('/status', function (Request $request, Response $response) {
        return jsonResponse($response, [
            'authenticated' => SessionManager::isAuthenticated(),
            'user' => SessionManager::getUser(),
            'csrf_token' => SessionManager::isAuthenticated() ? SessionManager::getCsrfToken() : null
        ]);
    });

    $group->get('/csrf', function (Request $request, Response $response) {
        if (!SessionManager::isAuthenticated()) {
            return jsonResponse($response, ['error' => 'Not authenticated'], 401);
        }
        return jsonResponse($response, ['csrf_token' => SessionManager::getCsrfToken()]);
    });
});

// Graph routes
$app->get('/api.php', function (Request $request, Response $response) use ($api) {
    return jsonResponse($response, $api->getGraph());
});

$app->get('/api.php/graph', function (Request $request, Response $response) use ($api) {
    return jsonResponse($response, $api->getGraph());
});

// Node routes
$app->group('/api.php/nodes', function (RouteCollectorProxy $group) use ($api) {
    $group->post('', function (Request $request, Response $response) use ($api) {
        $body = $request->getParsedBody();
        if (!isset($body['id']) || !isset($body['data'])) {
            return jsonResponse($response, ['error' => 'Missing required fields: id, data'], 400);
        }
        $result = $api->createNode($body['id'], $body['data']);
        return handleApiResult($response, $result);
    });

    $group->get('/{id}', function (Request $request, Response $response, array $args) use ($api) {
        return jsonResponse($response, $api->nodeExists(urldecode($args['id'])));
    });

    $group->put('/{id}', function (Request $request, Response $response, array $args) use ($api) {
        $body = $request->getParsedBody();
        if (!isset($body['data'])) {
            return jsonResponse($response, ['error' => 'Missing required field: data'], 400);
        }
        $result = $api->updateNode(urldecode($args['id']), $body['data']);
        return handleApiResult($response, $result);
    });

    $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($api) {
        $result = $api->removeNode(urldecode($args['id']));
        return handleApiResult($response, $result);
    });

    // Node status routes
    $group->get('/{id}/status', function (Request $request, Response $response, array $args) use ($api) {
        $result = $api->getNodeStatus(urldecode($args['id']));
        return handleApiResult($response, $result);
    });

    $group->get('/{id}/status/history', function (Request $request, Response $response, array $args) use ($api) {
        return jsonResponse($response, $api->getNodeStatusHistory(urldecode($args['id'])));
    });

    $group->post('/{id}/status', function (Request $request, Response $response, array $args) use ($api) {
        $body = $request->getParsedBody();
        if (!isset($body['status'])) {
            return jsonResponse($response, ['error' => 'Missing required field: status'], 400);
        }
        $result = $api->setNodeStatus(urldecode($args['id']), $body['status']);
        return handleApiResult($response, $result);
    });
});

// Edge routes
$app->group('/api.php/edges', function (RouteCollectorProxy $group) use ($api) {
    $group->post('', function (Request $request, Response $response) use ($api) {
        $body = $request->getParsedBody();
        if (!isset($body['id']) || !isset($body['source']) || !isset($body['target'])) {
            return jsonResponse($response, ['error' => 'Missing required fields: id, source, target'], 400);
        }
        $result = $api->createEdge($body['id'], $body['source'], $body['target'], $body['data'] ?? []);
        return handleApiResult($response, $result);
    });

    $group->get('/{id}', function (Request $request, Response $response, array $args) use ($api) {
        return jsonResponse($response, $api->edgeExists(urldecode($args['id'])));
    });

    $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($api) {
        $result = $api->removeEdge(urldecode($args['id']));
        return handleApiResult($response, $result);
    });

    $group->delete('/from/{source}', function (Request $request, Response $response, array $args) use ($api) {
        $result = $api->removeEdgesFrom(urldecode($args['source']));
        return handleApiResult($response, $result);
    });
});

// Backup routes
$app->post('/api.php/backup', function (Request $request, Response $response) use ($api) {
    $body = $request->getParsedBody();
    $result = $api->createBackup($body['name'] ?? null);
    return handleApiResult($response, $result);
});

// Audit routes
$app->get('/api.php/audit', function (Request $request, Response $response) use ($api) {
    $params = $request->getQueryParams();
    return jsonResponse($response, $api->getAuditHistory(
        $params['entity_type'] ?? null,
        $params['entity_id'] ?? null
    ));
});

// Restore routes
$app->group('/api.php/restore', function (RouteCollectorProxy $group) use ($api) {
    $group->post('/entity', function (Request $request, Response $response) use ($api) {
        $body = $request->getParsedBody();
        if (!isset($body['entity_type']) || !isset($body['entity_id']) || !isset($body['audit_log_id'])) {
            return jsonResponse($response, ['error' => 'Missing required fields'], 400);
        }
        $result = $api->restoreEntity($body['entity_type'], $body['entity_id'], (int)$body['audit_log_id']);
        return handleApiResult($response, $result);
    });

    $group->post('/timestamp', function (Request $request, Response $response) use ($api) {
        $body = $request->getParsedBody();
        if (!isset($body['timestamp'])) {
            return jsonResponse($response, ['error' => 'Missing required field: timestamp'], 400);
        }
        $result = $api->restoreToTimestamp($body['timestamp']);
        return handleApiResult($response, $result);
    });
});

// Status routes
$app->get('/api.php/status', function (Request $request, Response $response) use ($api) {
    return jsonResponse($response, $api->getAllNodeStatuses());
});

$app->get('/api.php/status/allowed', function (Request $request, Response $response) use ($api) {
    return jsonResponse($response, $api->getAllowedStatuses());
});

// Run app
$app->run();
