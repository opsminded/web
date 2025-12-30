<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Status\GetAllNodeStatusesAction;
use Internet\Graph\Action\Status\GetAllowedStatusesAction;
use Internet\Graph\Graph;
use Internet\Graph\NodeStatus;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class StatusActionsTest extends TestCase
{
    // GetAllNodeStatusesAction Tests
    public function test_get_all_node_statuses(): void
    {
        $mockStatus = $this->createMock(NodeStatus::class);
        $mockStatus->method('to_array')->willReturn([
            'node_id' => 'node1',
            'status' => 'healthy'
        ]);

        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('status')
            ->willReturn([$mockStatus]);

        $action = new GetAllNodeStatusesAction($graph);
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('statuses', $data);
        $this->assertCount(1, $data['statuses']);
        $this->assertEquals('healthy', $data['statuses'][0]['status']);
    }

    // GetAllowedStatusesAction Tests
    public function test_get_allowed_statuses(): void
    {
        $action = new GetAllowedStatusesAction();
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('allowed_statuses', $data);
        $this->assertEquals(['unknown', 'healthy', 'unhealthy', 'maintenance'], $data['allowed_statuses']);
    }
}
