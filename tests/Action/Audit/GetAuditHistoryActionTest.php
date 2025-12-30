<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Audit\GetAuditHistoryAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class GetAuditHistoryActionTest extends TestCase
{
    public function test_get_audit_history_all(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('get_audit_history')
            ->with(null, null)
            ->willReturn([['id' => 1, 'action' => 'create']]);

        $action = new GetAuditHistoryAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn([]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('audit_log', $data);
        $this->assertCount(1, $data['audit_log']);
    }

    public function test_get_audit_history_filtered(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('get_audit_history')
            ->with('node', 'node1')
            ->willReturn([]);

        $action = new GetAuditHistoryAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn([
            'entity_type' => 'node',
            'entity_id' => 'node1'
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
    }
}
