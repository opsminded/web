<?php declare(strict_types=1);

namespace Internet\Graph\Tests;

use Internet\Graph\Router;
use Internet\Graph\Http\Request;
use Internet\Graph\Http\Response;
use Internet\Graph\ApiHandler;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase {
    private Router $router;

    protected function setUp(): void {
        $this->router = new Router();
    }

    public function test_match_simple_route(): void {
        $request = new Request('GET', '/api.php/graph', ['api.php', 'graph'], [], null, []);
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals('graph.get', $match['name']);
        $this->assertEquals([ApiHandler::class, 'getGraph'], $match['handler']);
    }

    public function test_match_route_with_parameter(): void {
        $request = new Request('GET', '/api.php/nodes/foo-123', ['api.php', 'nodes', 'foo-123'], [], null, []);
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals('node.get', $match['name']);
        $this->assertEquals(['id' => 'foo-123'], $match['params']);
    }

    public function test_match_url_decodes_parameters(): void {
        $request = new Request('GET', '/api.php/nodes/foo%20bar', ['api.php', 'nodes', 'foo%20bar'], [], null, []);
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals(['id' => 'foo bar'], $match['params']);
    }

    public function test_match_longest_pattern_first(): void {
        $request = new Request(
            'GET',
            '/api.php/nodes/123/status/history',
            ['api.php', 'nodes', '123', 'status', 'history'],
            [],
            null,
            []
        );
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals('node.status.history', $match['name']);
    }

    public function test_match_status_vs_status_history(): void {
        // Test that /nodes/{id}/status matches correctly (not /status/history)
        $request = new Request(
            'GET',
            '/api.php/nodes/123/status',
            ['api.php', 'nodes', '123', 'status'],
            [],
            null,
            []
        );
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals('node.status.get', $match['name']);
    }

    public function test_match_edge_from_route(): void {
        $request = new Request(
            'DELETE',
            '/api.php/edges/from/source-node',
            ['api.php', 'edges', 'from', 'source-node'],
            [],
            null,
            []
        );
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals('edge.from.delete', $match['name']);
        $this->assertEquals(['source' => 'source-node'], $match['params']);
    }

    public function test_match_returns_null_for_unknown_route(): void {
        $request = new Request('GET', '/unknown/route', ['unknown', 'route'], [], null, []);
        $match = $this->router->match($request);

        $this->assertNull($match);
    }

    public function test_match_returns_null_for_wrong_method(): void {
        // POST to a GET-only route
        $request = new Request('POST', '/api.php/graph', ['api.php', 'graph'], [], null, []);
        $match = $this->router->match($request);

        $this->assertNull($match);
    }

    public function test_match_auth_routes(): void {
        $routes = [
            'POST /api.php/auth/login' => 'auth.login',
            'POST /api.php/auth/logout' => 'auth.logout',
            'GET /api.php/auth/status' => 'auth.status',
            'GET /api.php/auth/csrf' => 'auth.csrf',
        ];

        foreach ($routes as $methodPath => $expectedName) {
            [$method, $path] = explode(' ', $methodPath);
            $segments = array_filter(explode('/', $path));
            $request = new Request($method, $path, $segments, [], null, []);
            $match = $this->router->match($request);

            $this->assertNotNull($match, "Failed to match $methodPath");
            $this->assertEquals($expectedName, $match['name']);
        }
    }

    public function test_dispatch_returns_404_for_unknown_route(): void {
        $request = new Request('GET', '/unknown', ['unknown'], [], null, []);
        $response = $this->router->dispatch($request, []);

        $this->assertEquals(404, $response->status);
        $this->assertArrayHasKey('error', $response->data);
    }

    public function test_middleware_execution_order(): void {
        $executionOrder = [];

        $middleware1 = function(Request $request, callable $next, array $context) use (&$executionOrder) {
            $executionOrder[] = 'middleware1_before';
            $response = $next($request);
            $executionOrder[] = 'middleware1_after';
            return $response;
        };

        $middleware2 = function(Request $request, callable $next, array $context) use (&$executionOrder) {
            $executionOrder[] = 'middleware2_before';
            $response = $next($request);
            $executionOrder[] = 'middleware2_after';
            return $response;
        };

        $this->router->addMiddleware($middleware1);
        $this->router->addMiddleware($middleware2);

        // Create a request that will return 404 (middleware should still execute)
        $request = new Request('GET', '/unknown', ['unknown'], [], null, []);
        $this->router->dispatch($request, []);

        // Middleware should execute in order: 1 before, 2 before, handler (404), 2 after, 1 after
        $this->assertEquals([
            'middleware1_before',
            'middleware2_before',
            'middleware2_after',
            'middleware1_after'
        ], $executionOrder);
    }
}
