<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Node\UpdateNodeAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class UpdateNodeActionTest extends TestCase
{
    public function test_update_node_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('update_node')
            ->with('node1', ['name' => 'Updated'])
            ->willReturn(true);

        $action = new UpdateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'data' => ['name' => 'Updated']
        ]);

        $response = new Response();
        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
    }

    public function test_update_node_missing_data(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('update_node');

        $action = new UpdateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([]);

        $response = new Response();
        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(400, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertStringContainsString('Missing required field', $data['error']);
    }

    public function test_update_node_invalid_category(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('update_node');

        $action = new UpdateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'data' => ['category' => 'invalid']
        ]);

        $response = new Response();
        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(400, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertStringContainsString('Invalid category', $data['error']);
    }

    public function test_update_node_not_found(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('update_node')
            ->willReturn(false);

        $action = new UpdateNodeAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'data' => ['name' => 'Updated']
        ]);

        $response = new Response();
        $result = $action($request, $response, ['id' => 'nonexistent']);

        $this->assertEquals(404, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertFalse($data['success']);
    }
}
