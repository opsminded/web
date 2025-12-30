<?php declare(strict_types=1);

namespace Internet\Graph;

use Internet\Graph\Http\Request;
use Internet\Graph\Http\Response;

/**
 * HTTP Router
 *
 * Routes HTTP requests to handlers using a segment-based matching algorithm.
 * Supports middleware pipeline and dynamic route parameters.
 */
class Router {
    /** @var array<callable> Global middleware stack */
    private array $middleware = [];

    /**
     * Route table
     *
     * Order matters: longest patterns matched first
     * Pattern format: /literal/{param}/literal
     */
    private const ROUTES = [
        // Authentication routes (special handlers)
        'auth.login' => [
            'method' => 'POST',
            'pattern' => '/api.php/auth/login',
            'handler' => 'handleLogin',
        ],
        'auth.logout' => [
            'method' => 'POST',
            'pattern' => '/api.php/auth/logout',
            'handler' => 'handleLogout',
        ],
        'auth.status' => [
            'method' => 'GET',
            'pattern' => '/api.php/auth/status',
            'handler' => 'handleAuthStatus',
        ],
        'auth.csrf' => [
            'method' => 'GET',
            'pattern' => '/api.php/auth/csrf',
            'handler' => 'handleCsrf',
        ],

        // Graph routes
        'root' => [
            'method' => 'GET',
            'pattern' => '/api.php',
            'handler' => [ApiHandler::class, 'getGraph'],
        ],
        'graph.get' => [
            'method' => 'GET',
            'pattern' => '/api.php/graph',
            'handler' => [ApiHandler::class, 'getGraph'],
        ],

        // Node routes (longest patterns first)
        'node.status.history' => [
            'method' => 'GET',
            'pattern' => '/api.php/nodes/{id}/status/history',
            'handler' => [ApiHandler::class, 'getNodeStatusHistory'],
        ],
        'node.status.get' => [
            'method' => 'GET',
            'pattern' => '/api.php/nodes/{id}/status',
            'handler' => [ApiHandler::class, 'getNodeStatus'],
        ],
        'node.status.set' => [
            'method' => 'POST',
            'pattern' => '/api.php/nodes/{id}/status',
            'handler' => [ApiHandler::class, 'setNodeStatus'],
        ],
        'node.create' => [
            'method' => 'POST',
            'pattern' => '/api.php/nodes',
            'handler' => [ApiHandler::class, 'createNode'],
        ],
        'node.get' => [
            'method' => 'GET',
            'pattern' => '/api.php/nodes/{id}',
            'handler' => [ApiHandler::class, 'nodeExists'],
        ],
        'node.update' => [
            'method' => 'PUT',
            'pattern' => '/api.php/nodes/{id}',
            'handler' => [ApiHandler::class, 'updateNode'],
        ],
        'node.delete' => [
            'method' => 'DELETE',
            'pattern' => '/api.php/nodes/{id}',
            'handler' => [ApiHandler::class, 'removeNode'],
        ],

        // Edge routes (longest patterns first)
        'edge.from.delete' => [
            'method' => 'DELETE',
            'pattern' => '/api.php/edges/from/{source}',
            'handler' => [ApiHandler::class, 'removeEdgesFrom'],
        ],
        'edge.create' => [
            'method' => 'POST',
            'pattern' => '/api.php/edges',
            'handler' => [ApiHandler::class, 'createEdge'],
        ],
        'edge.get' => [
            'method' => 'GET',
            'pattern' => '/api.php/edges/{id}',
            'handler' => [ApiHandler::class, 'edgeExists'],
        ],
        'edge.delete' => [
            'method' => 'DELETE',
            'pattern' => '/api.php/edges/{id}',
            'handler' => [ApiHandler::class, 'removeEdge'],
        ],

        // Backup routes
        'backup.create' => [
            'method' => 'POST',
            'pattern' => '/api.php/backup',
            'handler' => [ApiHandler::class, 'createBackup'],
        ],

        // Audit routes
        'audit.get' => [
            'method' => 'GET',
            'pattern' => '/api.php/audit',
            'handler' => [ApiHandler::class, 'getAuditHistory'],
        ],

        // Restore routes
        'restore.entity' => [
            'method' => 'POST',
            'pattern' => '/api.php/restore/entity',
            'handler' => [ApiHandler::class, 'restoreEntity'],
        ],
        'restore.timestamp' => [
            'method' => 'POST',
            'pattern' => '/api.php/restore/timestamp',
            'handler' => [ApiHandler::class, 'restoreToTimestamp'],
        ],

        // Status routes
        'status.all' => [
            'method' => 'GET',
            'pattern' => '/api.php/status',
            'handler' => [ApiHandler::class, 'getAllNodeStatuses'],
        ],
        'status.allowed' => [
            'method' => 'GET',
            'pattern' => '/api.php/status/allowed',
            'handler' => [ApiHandler::class, 'getAllowedStatuses'],
        ],
    ];

    /**
     * Match request to route
     *
     * Returns route match with handler and parameters, or null if no match.
     *
     * @return array{name: string, handler: callable|array|string, params: array<string, string>}|null
     */
    public function match(Request $request): ?array {
        foreach (self::ROUTES as $name => $route) {
            // Check HTTP method
            if ($route['method'] !== $request->method) {
                continue;
            }

            // Try to match pattern
            $params = $this->matchPattern($route['pattern'], $request->path);
            if ($params !== null) {
                return [
                    'name' => $name,
                    'handler' => $route['handler'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    /**
     * Match URL pattern against path
     *
     * Supports literal segments and {param} placeholders.
     * Returns extracted parameters with URL decoding, or null if no match.
     *
     * @return array<string, string>|null
     */
    private function matchPattern(string $pattern, string $path): ?array {
        // Split into segments (filter empty)
        $patternSegments = array_filter(explode('/', $pattern));
        $pathSegments = array_filter(explode('/', $path));

        // Must have same number of segments
        if (count($patternSegments) !== count($pathSegments)) {
            return null;
        }

        $params = [];
        $patternSegments = array_values($patternSegments);
        $pathSegments = array_values($pathSegments);

        foreach ($patternSegments as $i => $segment) {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                // Parameter segment: extract and URL-decode
                $name = substr($segment, 1, -1);
                $params[$name] = urldecode($pathSegments[$i]);
            } elseif ($segment !== $pathSegments[$i]) {
                // Literal segment mismatch
                return null;
            }
        }

        return $params;
    }

    /**
     * Dispatch request through middleware pipeline to handler
     *
     * @param array<string, mixed> $context Shared context (apiHandler, user_id, etc.)
     */
    public function dispatch(Request $request, array $context): Response {
        // Match route
        $match = $this->match($request);

        // Build handler (404 or matched route)
        $handler = function(Request $req) use ($match, $request, $context): Response {
            if ($match === null) {
                return Response::notFound($request->path, $request->method);
            }

            // Inject route parameters into request
            $req = $req->withParams($match['params']);

            // Execute route handler
            $routeHandler = $this->wrapHandler($match['handler'], $req, $context);
            return $routeHandler($req);
        };

        // Apply global middleware (middleware wraps the handler)
        foreach (array_reverse($this->middleware) as $middleware) {
            $prevHandler = $handler;
            $handler = fn(Request $req): Response => $middleware($req, $prevHandler, $context);
        }

        // Execute middleware pipeline
        return $handler($request);
    }

    /**
     * Wrap handler as callable that returns Response
     *
     * Handles three types of handlers:
     * 1. String method name (special auth handlers)
     * 2. Array [class, method] (ApiHandler methods)
     * 3. Callable (closures)
     *
     * @param callable|array<int, string>|string $handler
     * @param array<string, mixed> $context
     */
    private function wrapHandler(callable|array|string $handler, Request $request, array $context): callable {
        return function(Request $req) use ($handler, $request, $context): Response {
            // Special auth handlers (string method names)
            if (is_string($handler)) {
                return $this->$handler($req, $context);
            }

            // ApiHandler methods [class, method]
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $instance = $context['apiHandler'];

                // Extract method arguments from request
                $args = $this->extractArgs($req, $method);

                // Call ApiHandler method
                $result = $instance->$method(...$args);

                // Convert ApiHandler result to Response
                return $this->formatResponse($result, $method);
            }

            // Callable (closures)
            return $handler($req, $context);
        };
    }

    /**
     * Extract method arguments from request based on method signature
     *
     * Maps request data to ApiHandler method parameters.
     *
     * @return array<int, mixed>
     */
    private function extractArgs(Request $request, string $method): array {
        // Map methods to their argument extraction logic
        return match($method) {
            // Node operations
            'createNode' => [
                $request->bodyParam('id'),
                $request->bodyParam('data')
            ],
            'nodeExists' => [
                $request->param('id')
            ],
            'updateNode' => [
                $request->param('id'),
                $request->bodyParam('data')
            ],
            'removeNode' => [
                $request->param('id')
            ],

            // Edge operations
            'createEdge' => [
                $request->bodyParam('id'),
                $request->bodyParam('source'),
                $request->bodyParam('target'),
                $request->bodyParam('data') ?? []
            ],
            'edgeExists' => [
                $request->param('id')
            ],
            'removeEdge' => [
                $request->param('id')
            ],
            'removeEdgesFrom' => [
                $request->param('source')
            ],

            // Status operations
            'getNodeStatus' => [
                $request->param('id')
            ],
            'getNodeStatusHistory' => [
                $request->param('id')
            ],
            'setNodeStatus' => [
                $request->param('id'),
                $request->bodyParam('status')
            ],

            // Graph operations
            'getGraph' => [],

            // Backup operations
            'createBackup' => [
                $request->bodyParam('name')
            ],

            // Audit operations
            'getAuditHistory' => [
                $request->queryParam('entity_type'),
                $request->queryParam('entity_id')
            ],

            // Restore operations
            'restoreEntity' => [
                $request->bodyParam('entity_type'),
                $request->bodyParam('entity_id'),
                (int)$request->bodyParam('audit_log_id')
            ],
            'restoreToTimestamp' => [
                $request->bodyParam('timestamp')
            ],

            // Status constants
            'getAllNodeStatuses' => [],
            'getAllowedStatuses' => [],

            default => []
        };
    }

    /**
     * Convert ApiHandler result to Response object
     *
     * Handles two response formats:
     * 1. Standard format: {success: bool, error?: string, code?: int, data?: mixed, message?: string}
     * 2. Direct data format: plain arrays (for getGraph, nodeExists, etc.)
     *
     * @param array $result
     */
    private function formatResponse(array $result, string $method): Response {
        // Direct data methods that return plain arrays (not success/error format)
        $directDataMethods = ['getGraph', 'nodeExists', 'edgeExists', 'getAuditHistory', 'getNodeStatusHistory', 'getAllNodeStatuses', 'getAllowedStatuses'];

        if (in_array($method, $directDataMethods)) {
            return new Response(200, $result);
        }

        // Standard success/error format
        if (isset($result['success']) && $result['success']) {
            return Response::success(
                $result['data'] ?? null,
                $result['message'] ?? 'Success'
            );
        } else {
            return Response::error(
                $result['code'] ?? 500,
                $result['error'] ?? 'Operation failed',
                $result['details'] ?? null
            );
        }
    }

    /**
     * Add global middleware to pipeline
     *
     * Middleware signature: function(Request $request, callable $next, array $context): Response
     */
    public function addMiddleware(callable $middleware): void {
        $this->middleware[] = $middleware;
    }

    // Special handlers for authentication routes

    private function handleLogin(Request $request, array $context): Response {
        $username = $request->bodyParam('username');
        $password = $request->bodyParam('password');

        if (!$username || !$password) {
            return Response::badRequest('Missing username or password');
        }

        // Validate credentials
        $validUsers = $context['validUsers'] ?? [];
        if (isset($validUsers[$username]) && password_verify($password, $validUsers[$username])) {
            SessionManager::setUser($username);
            return Response::success([
                'user' => $username,
                'csrf_token' => SessionManager::getCsrfToken()
            ], 'Login successful');
        }

        return Response::unauthorized('Invalid credentials');
    }

    private function handleLogout(Request $request, array $context): Response {
        SessionManager::destroy();
        return Response::success(null, 'Logout successful');
    }

    private function handleAuthStatus(Request $request, array $context): Response {
        return new Response(200, [
            'authenticated' => SessionManager::isAuthenticated(),
            'user' => SessionManager::getUser(),
            'csrf_token' => SessionManager::isAuthenticated() ? SessionManager::getCsrfToken() : null
        ]);
    }

    private function handleCsrf(Request $request, array $context): Response {
        if (!SessionManager::isAuthenticated()) {
            return Response::unauthorized('Not authenticated');
        }
        return new Response(200, ['csrf_token' => SessionManager::getCsrfToken()]);
    }
}
