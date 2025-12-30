<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Node\SetNodeStatusAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class SetNodeStatusActionTest extends TestCase
{
    public function test_set_node_status_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('set_node_status')
            ->with('node1', 'healthy')
            ->willReturn(true);

        $action = new SetNodeStatusAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn(['status' => 'healthy']);

        $response = new Response();
        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('healthy', $data['data']['status']);
    }

    public function test_set_node_status_missing_status(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('set_node_status');

        $action = new SetNodeStatusAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([]);

        $response = new Response();
        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(400, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertStringContainsString('Missing required field', $data['error']);
    }

    public function test_set_node_status_invalid_status(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('set_node_status');

        $action = new SetNodeStatusAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn(['status' => 'invalid']);

        $response = new Response();
        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(400, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertStringContainsString('Invalid status', $data['error']);
    }

    public function test_set_node_status_node_not_found(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('set_node_status')
            ->willReturn(false);

        $action = new SetNodeStatusAction($graph);

        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn(['status' => 'healthy']);

        $response = new Response();
        $result = $action($request, $response, ['id' => 'nonexistent']);

        $this->assertEquals(404, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertFalse($data['success']);
    }
}
