<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Node\CreateNodeAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class CreateNodeActionTest extends TestCase
{
    public function test_create_node_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('add_node')
            ->with('node1', ['category' => 'business', 'type' => 'server', 'name' => 'Test'])
            ->willReturn(true);

        $action = new CreateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'id' => 'node1',
            'data' => ['category' => 'business', 'type' => 'server', 'name' => 'Test']
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('node1', $data['data']['id']);
    }

    public function test_create_node_missing_id(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('add_node');

        $action = new CreateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'data' => ['category' => 'business', 'type' => 'server']
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(400, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertStringContainsString('Missing required fields', $data['error']);
    }

    public function test_create_node_missing_category(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('add_node');

        $action = new CreateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'id' => 'node1',
            'data' => ['type' => 'server']
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(400, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertStringContainsString('Category is required', $data['error']);
    }

    public function test_create_node_invalid_category(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('add_node');

        $action = new CreateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'id' => 'node1',
            'data' => ['category' => 'invalid', 'type' => 'server']
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(400, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertStringContainsString('Invalid category', $data['error']);
    }

    public function test_create_node_invalid_type(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('add_node');

        $action = new CreateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'id' => 'node1',
            'data' => ['category' => 'business', 'type' => 'invalid']
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(400, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertStringContainsString('Invalid type', $data['error']);
    }

    public function test_create_node_already_exists(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('add_node')
            ->willReturn(false);

        $action = new CreateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'id' => 'node1',
            'data' => ['category' => 'business', 'type' => 'server']
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(409, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertFalse($data['success']);
    }
}
