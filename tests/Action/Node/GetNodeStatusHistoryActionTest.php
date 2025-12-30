<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Node\GetNodeStatusHistoryAction;
use Internet\Graph\Graph;
use Internet\Graph\NodeStatus;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class GetNodeStatusHistoryActionTest extends TestCase
{
    public function test_get_node_status_history(): void
    {
        $mockStatus1 = $this->createMock(NodeStatus::class);
        $mockStatus1->method('to_array')->willReturn([
            'node_id' => 'node1',
            'status' => 'unknown',
            'created_at' => '2025-12-30 10:00:00'
        ]);

        $mockStatus2 = $this->createMock(NodeStatus::class);
        $mockStatus2->method('to_array')->willReturn([
            'node_id' => 'node1',
            'status' => 'healthy',
            'created_at' => '2025-12-30 12:00:00'
        ]);

        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('get_node_status_history')
            ->with('node1')
            ->willReturn([$mockStatus1, $mockStatus2]);

        $action = new GetNodeStatusHistoryAction($graph);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertCount(2, $data['history']);
        $this->assertEquals('unknown', $data['history'][0]['status']);
        $this->assertEquals('healthy', $data['history'][1]['status']);
    }

    public function test_get_node_status_history_empty(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('get_node_status_history')
            ->with('node1')
            ->willReturn([]);

        $action = new GetNodeStatusHistoryAction($graph);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertCount(0, $data['history']);
        $this->assertIsArray($data['history']);
    }
}
