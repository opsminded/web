<?php declare(strict_types=1);

namespace Internet\Graph\Tests;

use Internet\Graph\ApiHandler;
use Internet\Graph\Graph;
use PHPUnit\Framework\TestCase;

class ApiHandlerTest extends TestCase {
    private Graph $mockGraph;
    private ApiHandler $apiHandler;

    protected function setUp(): void {
        parent::setUp();

        $this->mockGraph = $this->createMock(Graph::class);
        $this->apiHandler = new ApiHandler($this->mockGraph);
    }

    public function testGetGraph(): void {
        $expectedData = [
            'nodes' => [['data' => ['id' => 'node1']]],
            'edges' => [['data' => ['id' => 'edge1']]]
        ];

        $this->mockGraph->expects($this->once())
            ->method('get')
            ->willReturn($expectedData);

        $result = $this->apiHandler->getGraph();

        $this->assertEquals($expectedData, $result);
    }

    public function testNodeExistsTrue(): void {
        $this->mockGraph->expects($this->once())
            ->method('node_exists')
            ->with('node1')
            ->willReturn(true);

        $result = $this->apiHandler->nodeExists('node1');

        $this->assertTrue($result['exists']);
        $this->assertEquals('node1', $result['id']);
    }

    public function testNodeExistsFalse(): void {
        $this->mockGraph->expects($this->once())
            ->method('node_exists')
            ->with('node1')
            ->willReturn(false);

        $result = $this->apiHandler->nodeExists('node1');

        $this->assertFalse($result['exists']);
        $this->assertEquals('node1', $result['id']);
    }

    public function testCreateNodeSuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('add_node')
            ->with('node1', ['label' => 'Test'])
            ->willReturn(true);

        $result = $this->apiHandler->createNode('node1', ['label' => 'Test']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Node created successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['id']);
    }

    public function testCreateNodeFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('add_node')
            ->with('node1', ['label' => 'Test'])
            ->willReturn(false);

        $result = $this->apiHandler->createNode('node1', ['label' => 'Test']);

        $this->assertFalse($result['success']);
        $this->assertEquals('Node already exists or creation failed', $result['error']);
        $this->assertEquals(409, $result['code']);
    }

    public function testUpdateNodeSuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('update_node')
            ->with('node1', ['label' => 'Updated'])
            ->willReturn(true);

        $result = $this->apiHandler->updateNode('node1', ['label' => 'Updated']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Node updated successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['id']);
    }

    public function testUpdateNodeFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('update_node')
            ->with('node1', ['label' => 'Updated'])
            ->willReturn(false);

        $result = $this->apiHandler->updateNode('node1', ['label' => 'Updated']);

        $this->assertFalse($result['success']);
        $this->assertEquals('Node not found or update failed', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testRemoveNodeSuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('remove_node')
            ->with('node1')
            ->willReturn(true);

        $result = $this->apiHandler->removeNode('node1');

        $this->assertTrue($result['success']);
        $this->assertEquals('Node removed successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['id']);
    }

    public function testRemoveNodeFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('remove_node')
            ->with('node1')
            ->willReturn(false);

        $result = $this->apiHandler->removeNode('node1');

        $this->assertFalse($result['success']);
        $this->assertEquals('Node not found or removal failed', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testEdgeExistsTrue(): void {
        $this->mockGraph->expects($this->once())
            ->method('edge_exists_by_id')
            ->with('edge1')
            ->willReturn(true);

        $result = $this->apiHandler->edgeExists('edge1');

        $this->assertTrue($result['exists']);
        $this->assertEquals('edge1', $result['id']);
    }

    public function testEdgeExistsFalse(): void {
        $this->mockGraph->expects($this->once())
            ->method('edge_exists_by_id')
            ->with('edge1')
            ->willReturn(false);

        $result = $this->apiHandler->edgeExists('edge1');

        $this->assertFalse($result['exists']);
        $this->assertEquals('edge1', $result['id']);
    }

    public function testCreateEdgeSuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('add_edge')
            ->with('edge1', 'node1', 'node2', ['label' => 'Test'])
            ->willReturn(true);

        $result = $this->apiHandler->createEdge('edge1', 'node1', 'node2', ['label' => 'Test']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Edge created successfully', $result['message']);
        $this->assertEquals('edge1', $result['data']['id']);
    }

    public function testCreateEdgeFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('add_edge')
            ->with('edge1', 'node1', 'node2', [])
            ->willReturn(false);

        $result = $this->apiHandler->createEdge('edge1', 'node1', 'node2');

        $this->assertFalse($result['success']);
        $this->assertEquals('Edge creation failed', $result['error']);
        $this->assertEquals(400, $result['code']);
    }

    public function testRemoveEdgeSuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('remove_edge')
            ->with('edge1')
            ->willReturn(true);

        $result = $this->apiHandler->removeEdge('edge1');

        $this->assertTrue($result['success']);
        $this->assertEquals('Edge removed successfully', $result['message']);
        $this->assertEquals('edge1', $result['data']['id']);
    }

    public function testRemoveEdgeFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('remove_edge')
            ->with('edge1')
            ->willReturn(false);

        $result = $this->apiHandler->removeEdge('edge1');

        $this->assertFalse($result['success']);
        $this->assertEquals('Edge not found or removal failed', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testRemoveEdgesFromSuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('remove_edges_from')
            ->with('node1')
            ->willReturn(true);

        $result = $this->apiHandler->removeEdgesFrom('node1');

        $this->assertTrue($result['success']);
        $this->assertEquals('Edges removed successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['source']);
    }

    public function testRemoveEdgesFromFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('remove_edges_from')
            ->with('node1')
            ->willReturn(false);

        $result = $this->apiHandler->removeEdgesFrom('node1');

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to remove edges', $result['error']);
        $this->assertEquals(400, $result['code']);
    }

    public function testCreateBackupSuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('create_backup')
            ->with('test_backup')
            ->willReturn([
                'success' => true,
                'file' => '/path/to/backup.db',
                'backup_name' => 'test_backup'
            ]);

        $result = $this->apiHandler->createBackup('test_backup');

        $this->assertTrue($result['success']);
        $this->assertEquals('Backup created successfully', $result['message']);
        $this->assertArrayHasKey('data', $result);
    }

    public function testCreateBackupFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('create_backup')
            ->with(null)
            ->willReturn([
                'success' => false,
                'error' => 'Backup failed'
            ]);

        $result = $this->apiHandler->createBackup();

        $this->assertFalse($result['success']);
        $this->assertEquals('Backup failed', $result['error']);
        $this->assertEquals(500, $result['code']);
    }

    public function testGetAuditHistory(): void {
        $expectedHistory = [
            ['action' => 'create', 'entity_id' => 'node1'],
            ['action' => 'update', 'entity_id' => 'node1']
        ];

        $this->mockGraph->expects($this->once())
            ->method('get_audit_history')
            ->with('node', 'node1')
            ->willReturn($expectedHistory);

        $result = $this->apiHandler->getAuditHistory('node', 'node1');

        $this->assertArrayHasKey('audit_log', $result);
        $this->assertEquals($expectedHistory, $result['audit_log']);
    }

    public function testGetAuditHistoryNoFilters(): void {
        $expectedHistory = [
            ['action' => 'create', 'entity_id' => 'node1'],
            ['action' => 'create', 'entity_id' => 'edge1']
        ];

        $this->mockGraph->expects($this->once())
            ->method('get_audit_history')
            ->with(null, null)
            ->willReturn($expectedHistory);

        $result = $this->apiHandler->getAuditHistory();

        $this->assertArrayHasKey('audit_log', $result);
        $this->assertEquals($expectedHistory, $result['audit_log']);
    }

    public function testRestoreEntitySuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('restore_entity')
            ->with('node', 'node1', 123)
            ->willReturn(true);

        $result = $this->apiHandler->restoreEntity('node', 'node1', 123);

        $this->assertTrue($result['success']);
        $this->assertEquals('Entity restored successfully', $result['message']);
    }

    public function testRestoreEntityFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('restore_entity')
            ->with('node', 'node1', 123)
            ->willReturn(false);

        $result = $this->apiHandler->restoreEntity('node', 'node1', 123);

        $this->assertFalse($result['success']);
        $this->assertEquals('Restore failed', $result['error']);
        $this->assertEquals(400, $result['code']);
    }

    public function testRestoreToTimestampSuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('restore_to_timestamp')
            ->with('2024-01-01 12:00:00')
            ->willReturn(true);

        $result = $this->apiHandler->restoreToTimestamp('2024-01-01 12:00:00');

        $this->assertTrue($result['success']);
        $this->assertEquals('Graph restored to timestamp successfully', $result['message']);
    }

    public function testRestoreToTimestampFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('restore_to_timestamp')
            ->with('2024-01-01 12:00:00')
            ->willReturn(false);

        $result = $this->apiHandler->restoreToTimestamp('2024-01-01 12:00:00');

        $this->assertFalse($result['success']);
        $this->assertEquals('Restore failed', $result['error']);
        $this->assertEquals(400, $result['code']);
    }

    public function testGetAllNodeStatuses(): void {
        $mockStatus1 = $this->createMock(\Internet\Graph\NodeStatus::class);
        $mockStatus1->method('to_array')->willReturn([
            'node_id' => 'node1',
            'status' => 'online',
            'created_at' => '2024-01-01 12:00:00'
        ]);

        $mockStatus2 = $this->createMock(\Internet\Graph\NodeStatus::class);
        $mockStatus2->method('to_array')->willReturn([
            'node_id' => 'node2',
            'status' => 'offline',
            'created_at' => '2024-01-01 12:01:00'
        ]);

        $this->mockGraph->expects($this->once())
            ->method('status')
            ->willReturn([$mockStatus1, $mockStatus2]);

        $result = $this->apiHandler->getAllNodeStatuses();

        $this->assertArrayHasKey('statuses', $result);
        $this->assertCount(2, $result['statuses']);
        $this->assertEquals('node1', $result['statuses'][0]['node_id']);
        $this->assertEquals('online', $result['statuses'][0]['status']);
        $this->assertEquals('node2', $result['statuses'][1]['node_id']);
        $this->assertEquals('offline', $result['statuses'][1]['status']);
    }

    public function testGetAllNodeStatusesEmpty(): void {
        $this->mockGraph->expects($this->once())
            ->method('status')
            ->willReturn([]);

        $result = $this->apiHandler->getAllNodeStatuses();

        $this->assertArrayHasKey('statuses', $result);
        $this->assertEmpty($result['statuses']);
    }

    public function testGetNodeStatusSuccess(): void {
        $mockStatus = $this->createMock(\Internet\Graph\NodeStatus::class);
        $mockStatus->method('to_array')->willReturn([
            'node_id' => 'node1',
            'status' => 'online',
            'created_at' => '2024-01-01 12:00:00'
        ]);

        $this->mockGraph->expects($this->once())
            ->method('get_node_status')
            ->with('node1')
            ->willReturn($mockStatus);

        $result = $this->apiHandler->getNodeStatus('node1');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('node1', $result['data']['node_id']);
        $this->assertEquals('online', $result['data']['status']);
    }

    public function testGetNodeStatusNotFound(): void {
        $this->mockGraph->expects($this->once())
            ->method('get_node_status')
            ->with('node1')
            ->willReturn(null);

        $result = $this->apiHandler->getNodeStatus('node1');

        $this->assertFalse($result['success']);
        $this->assertEquals('No status found for node', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testSetNodeStatusSuccess(): void {
        $this->mockGraph->expects($this->once())
            ->method('set_node_status')
            ->with('node1', 'online')
            ->willReturn(true);

        $result = $this->apiHandler->setNodeStatus('node1', 'online');

        $this->assertTrue($result['success']);
        $this->assertEquals('Node status set successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['node_id']);
        $this->assertEquals('online', $result['data']['status']);
    }

    public function testSetNodeStatusFailure(): void {
        $this->mockGraph->expects($this->once())
            ->method('set_node_status')
            ->with('nonexistent', 'online')
            ->willReturn(false);

        $result = $this->apiHandler->setNodeStatus('nonexistent', 'online');

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to set node status (node may not exist)', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testGetNodeStatusHistory(): void {
        $mockStatus1 = $this->createMock(\Internet\Graph\NodeStatus::class);
        $mockStatus1->method('to_array')->willReturn([
            'node_id' => 'node1',
            'status' => 'online',
            'created_at' => '2024-01-01 12:00:00'
        ]);

        $mockStatus2 = $this->createMock(\Internet\Graph\NodeStatus::class);
        $mockStatus2->method('to_array')->willReturn([
            'node_id' => 'node1',
            'status' => 'offline',
            'created_at' => '2024-01-01 12:01:00'
        ]);

        $this->mockGraph->expects($this->once())
            ->method('get_node_status_history')
            ->with('node1')
            ->willReturn([$mockStatus1, $mockStatus2]);

        $result = $this->apiHandler->getNodeStatusHistory('node1');

        $this->assertArrayHasKey('history', $result);
        $this->assertCount(2, $result['history']);
        $this->assertEquals('online', $result['history'][0]['status']);
        $this->assertEquals('offline', $result['history'][1]['status']);
    }

    public function testGetNodeStatusHistoryEmpty(): void {
        $this->mockGraph->expects($this->once())
            ->method('get_node_status_history')
            ->with('node1')
            ->willReturn([]);

        $result = $this->apiHandler->getNodeStatusHistory('node1');

        $this->assertArrayHasKey('history', $result);
        $this->assertEmpty($result['history']);
    }
}
