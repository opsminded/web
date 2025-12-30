<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Internet\Graph\Action\Backup\CreateBackupAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;

class CreateBackupActionTest extends TestCase
{
    public function test_create_backup_success(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('create_backup')
            ->with('test_backup')
            ->willReturn(['success' => true, 'path' => '/path/to/backup.db']);

        $action = new CreateBackupAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn(['name' => 'test_backup']);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(200, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertTrue($data['success']);
    }

    public function test_create_backup_failure(): void
    {
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('create_backup')
            ->willReturn(['success' => false, 'error' => 'Failed']);

        $action = new CreateBackupAction($graph);
        $request = $this->createMock(Request::class);
        $request->method('getParsedBody')->willReturn([]);

        $response = new Response();
        $result = $action($request, $response, []);

        $this->assertEquals(500, $result->getStatusCode());
        $data = json_decode((string)$result->getBody(), true);
        $this->assertFalse($data['success']);
    }
}
