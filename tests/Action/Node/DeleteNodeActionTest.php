<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Node\DeleteNodeAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class DeleteNodeActionTest extends TestCase
{
    public function test_delete_node_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('remove_node')
            ->with('node1')
            ->willReturn(true);

        $action = new DeleteNodeAction($graph);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('node1', $data['data']['id']);
    }

    public function test_delete_node_not_found(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('remove_node')
            ->with('nonexistent')
            ->willReturn(false);

        $action = new DeleteNodeAction($graph);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'nonexistent']);

        $this->assertEquals(404, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertFalse($data['success']);
    }
}
