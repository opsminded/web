<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Edge\CreateEdgeAction;
use Internet\Graph\Action\Edge\GetEdgeAction;
use Internet\Graph\Action\Edge\DeleteEdgeAction;
use Internet\Graph\Action\Edge\DeleteEdgesFromAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

/**
 * Tests for Edge Actions
 */
class EdgeActionsTest extends TestCase
{
    // CreateEdgeAction Tests
    public function test_create_edge_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('add_edge')
            ->with('edge1', 'node1', 'node2', ['label' => 'connects'])
            ->willReturn(true);

        $action = new CreateEdgeAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'id' => 'edge1',
            'source' => 'node1',
            'target' => 'node2',
            'data' => ['label' => 'connects']
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
    }

    public function test_create_edge_missing_fields(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->never())->method('add_edge');

        $action = new CreateEdgeAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([
            'id' => 'edge1'
        ]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(400, $result->getStatusCode());
    }

    // GetEdgeAction Tests
    public function test_get_edge_exists(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('edge_exists_by_id')
            ->with('edge1')
            ->willReturn(true);

        $action = new GetEdgeAction($graph);
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'edge1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['exists']);
    }

    // DeleteEdgeAction Tests
    public function test_delete_edge_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('remove_edge')
            ->with('edge1')
            ->willReturn(true);

        $action = new DeleteEdgeAction($graph);
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'edge1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
    }

    // DeleteEdgesFromAction Tests
    public function test_delete_edges_from_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('remove_edges_from')
            ->with('node1')
            ->willReturn(true);

        $action = new DeleteEdgesFromAction($graph);
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['source' => 'node1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
    }
}
