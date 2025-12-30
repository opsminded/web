<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Node\GetNodeStatusAction;
use Internet\Graph\Graph;
use Internet\Graph\NodeStatus;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class GetNodeStatusActionTest extends TestCase
{
    public function test_get_node_status_success(): void
    {
        $mockStatus = $this->createMock(NodeStatus::class);
        $mockStatus->method('to_array')->willReturn([
            'node_id' => 'node1',
            'status' => 'healthy',
            'created_at' => '2025-12-30 12:00:00'
        ]);

        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('get_node_status')
            ->with('node1')
            ->willReturn($mockStatus);

        $action = new GetNodeStatusAction($graph);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('healthy', $data['data']['status']);
    }

    public function test_get_node_status_not_found(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('get_node_status')
            ->with('nonexistent')
            ->willReturn(null);

        $action = new GetNodeStatusAction($graph);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'nonexistent']);

        $this->assertEquals(404, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertFalse($data['success']);
    }
}
