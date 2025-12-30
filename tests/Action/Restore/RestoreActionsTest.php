<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Restore\RestoreEntityAction;
use Internet\Graph\Action\Restore\RestoreToTimestampAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class RestoreActionsTest extends TestCase
{
    // RestoreEntityAction Tests
    public function test_restore_entity_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('restore_entity')
            ->with('node', 'node1', 123)
            ->willReturn(true);

        $action = new RestoreEntityAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'entity_type' => 'node',
            'entity_id' => 'node1',
            'audit_log_id' => 123
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
    }

    public function test_restore_entity_missing_fields(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('restore_entity');

        $action = new RestoreEntityAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn(['entity_type' => 'node']);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(400, $result->getStatusCode());
    }

    // RestoreToTimestampAction Tests
    public function test_restore_to_timestamp_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('restore_to_timestamp')
            ->with('2025-12-30 12:00:00')
            ->willReturn(true);

        $action = new RestoreToTimestampAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'timestamp' => '2025-12-30 12:00:00'
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
    }

    public function test_restore_to_timestamp_missing_timestamp(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('restore_to_timestamp');

        $action = new RestoreToTimestampAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(400, $result->getStatusCode());
    }
}
