<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Middleware\ResponseTransformerMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Test for ResponseTransformerMiddleware
 */
class ResponseTransformerMiddlewareTest extends TestCase
{
    public function test_transforms_plain_data_response(): void
    {
        $middleware = new ResponseTransformerMiddleware();

        // Mock handler that returns plain data
        $handler = $this->createMock(RequestHandlerInterface::class);
        $originalResponse = new Response();
        $originalResponse->getBody()->write(json_encode(['nodes' => [], 'edges' => []]));
        $originalResponse = $originalResponse->withHeader('Content-Type', 'application/json');
        $handler->method('handle')->willReturn($originalResponse);

        $request = $this->createMock(Request::class);
        $result = $middleware->process($request, $handler);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);

        // Should have standard format
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('timestamp', $data['meta']);
        $this->assertArrayHasKey('version', $data['meta']);
        $this->assertEquals('1.0', $data['meta']['version']);
    }

    public function test_adds_meta_to_success_response(): void
    {
        $middleware = new ResponseTransformerMiddleware();

        // Mock handler that returns response with success field
        $handler = $this->createMock(RequestHandlerInterface::class);
        $originalResponse = new Response();
        $originalResponse->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Node created',
            'data' => ['id' => 'node1']
        ]));
        $originalResponse = $originalResponse->withHeader('Content-Type', 'application/json');
        $handler->method('handle')->willReturn($originalResponse);

        $request = $this->createMock(Request::class);
        $result = $middleware->process($request, $handler);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);

        // Should preserve structure and add meta
        $this->assertTrue($data['success']);
        $this->assertEquals('Node created', $data['message']);
        $this->assertEquals(['id' => 'node1'], $data['data']);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('timestamp', $data['meta']);
        $this->assertArrayHasKey('version', $data['meta']);
    }

    public function test_adds_meta_to_error_response(): void
    {
        $middleware = new ResponseTransformerMiddleware();

        // Mock handler that returns error response
        $handler = $this->createMock(RequestHandlerInterface::class);
        $originalResponse = new Response();
        $originalResponse->getBody()->write(json_encode(['error' => 'Not found']));
        $originalResponse = $originalResponse
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
        $handler->method('handle')->willReturn($originalResponse);

        $request = $this->createMock(Request::class);
        $result = $middleware->process($request, $handler);

        $this->assertEquals(404, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);

        // Should add success: false and meta
        $this->assertFalse($data['success']);
        $this->assertEquals('Not found', $data['error']);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_skips_already_transformed_response(): void
    {
        $middleware = new ResponseTransformerMiddleware();

        // Mock handler that returns already-transformed response
        $handler = $this->createMock(RequestHandlerInterface::class);
        $originalResponse = new Response();
        $originalResponse->getBody()->write(json_encode([
            'success' => true,
            'data' => ['test' => 'data'],
            'meta' => ['timestamp' => '2025-01-01T00:00:00Z', 'version' => '1.0']
        ]));
        $originalResponse = $originalResponse->withHeader('Content-Type', 'application/json');
        $handler->method('handle')->willReturn($originalResponse);

        $request = $this->createMock(Request::class);
        $result = $middleware->process($request, $handler);

        // Should not transform again
        $data = json_decode((string)$result->getBody(), true);
        $this->assertEquals('2025-01-01T00:00:00Z', $data['meta']['timestamp']);
    }

    public function test_skips_non_json_responses(): void
    {
        $middleware = new ResponseTransformerMiddleware();

        // Mock handler that returns HTML
        $handler = $this->createMock(RequestHandlerInterface::class);
        $originalResponse = new Response();
        $originalResponse->getBody()->write('<html>Test</html>');
        $originalResponse = $originalResponse->withHeader('Content-Type', 'text/html');
        $handler->method('handle')->willReturn($originalResponse);

        $request = $this->createMock(Request::class);
        $result = $middleware->process($request, $handler);

        // Should not transform
        $this->assertEquals('<html>Test</html>', (string)$result->getBody());
    }
}
