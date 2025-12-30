<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Graph\GetGraphAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

/**
 * Test for GetGraphAction
 */
class GetGraphActionTest extends TestCase
{
    public function test_invoke_returns_graph_data(): void
    {
        // Create mock Graph
        $graph = $this->createMock(Graph::class);
        $expectedData = [
            'nodes' => [
                ['id' => 'node1', 'data' => ['category' => 'business']],
                ['id' => 'node2', 'data' => ['category' => 'infrastructure']],
            ],
            'edges' => [
                ['id' => 'edge1', 'source' => 'node1', 'target' => 'node2'],
            ],
        ];
        $graph->expects($this->once())
            ->method('get')
            ->willReturn($expectedData);

        // Create action
        $action = new GetGraphAction($graph);

        // Create request and response
        $request = $this->createMock(Request::class);
        $response = new Response();

        // Execute action
        $result = $action($request, $response, []);

        // Assert response
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeaderLine('Content-Type'));

        $body = (string)$result->getBody();
        $data = json_decode($body, true);

        $this->assertEquals($expectedData, $data);
    }

    public function test_invoke_with_empty_graph(): void
    {
        // Create mock Graph
        $graph = $this->createMock(Graph::class);
        $expectedData = [
            'nodes' => [],
            'edges' => [],
        ];
        $graph->expects($this->once())
            ->method('get')
            ->willReturn($expectedData);

        // Create action
        $action = new GetGraphAction($graph);

        // Create request and response
        $request = $this->createMock(Request::class);
        $response = new Response();

        // Execute action
        $result = $action($request, $response, []);

        // Assert response
        $this->assertEquals(200, $result->getStatusCode());

        $body = (string)$result->getBody();
        $data = json_decode($body, true);

        $this->assertEquals($expectedData, $data);
        $this->assertIsArray($data['nodes']);
        $this->assertIsArray($data['edges']);
        $this->assertEmpty($data['nodes']);
        $this->assertEmpty($data['edges']);
    }
}
