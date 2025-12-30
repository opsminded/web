<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Node\GetNodeAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class GetNodeActionTest extends TestCase
{
    public function test_get_node_exists(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('node_exists')
            ->with('node1')
            ->willReturn(true);

        $action = new GetNodeAction($graph);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'node1']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['exists']);
        $this->assertEquals('node1', $data['id']);
    }

    public function test_get_node_not_exists(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('node_exists')
            ->with('nonexistent')
            ->willReturn(false);

        $action = new GetNodeAction($graph);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'nonexistent']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertFalse($data['exists']);
    }

    public function test_get_node_url_decode(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('node_exists')
            ->with('node with spaces')
            ->willReturn(true);

        $action = new GetNodeAction($graph);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $action($request, $response, ['id' => 'node%20with%20spaces']);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['exists']);
        $this->assertEquals('node with spaces', $data['id']);
    }
}
