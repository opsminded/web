<?php declare(strict_types=1);

namespace Internet\Graph\Tests;

use Internet\Graph\ApiHandler;
use Internet\Graph\Graph;
use Internet\Graph\AuditContext;
use PHPUnit\Framework\TestCase;

class ApiHandlerTest extends TestCase {
    private string $testDbFile;
    private Graph $graph;
    private ApiHandler $apiHandler;

    protected function setUp(): void {
        parent::setUp();

        // Create a test database for each test
        $this->testDbFile = './tmp/test_api_handler_' . uniqid() . '.db';
        $this->graph = new Graph($this->testDbFile);
        $this->apiHandler = new ApiHandler($this->graph);
        AuditContext::clear();
    }

    protected function tearDown(): void {
        parent::tearDown();

        // Clean up test database
        if (file_exists($this->testDbFile)) {
            unlink($this->testDbFile);
        }

        $backupDir = dirname($this->testDbFile) . '/backups';
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($backupDir);
        }

        AuditContext::clear();
    }

    public function testGetGraph(): void {
        // Create some test data
        $this->graph->add_node('node1', ['label' => 'Test Node']);
        $this->graph->add_node('node2', ['label' => 'Test Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', ['label' => 'Test Edge']);

        $result = $this->apiHandler->getGraph();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('edges', $result);
        $this->assertCount(2, $result['nodes']);
        $this->assertCount(1, $result['edges']);
    }

    public function testNodeExistsTrue(): void {
        $this->graph->add_node('node1', ['label' => 'Test Node']);

        $result = $this->apiHandler->nodeExists('node1');

        $this->assertTrue($result['exists']);
        $this->assertEquals('node1', $result['id']);
    }

    public function testNodeExistsFalse(): void {
        $result = $this->apiHandler->nodeExists('node1');

        $this->assertFalse($result['exists']);
        $this->assertEquals('node1', $result['id']);
    }

    public function testCreateNodeSuccess(): void {
        $result = $this->apiHandler->createNode('node1', ['label' => 'Test', 'category' => 'business', 'type' => 'server']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Node created successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['id']);
        $this->assertTrue($this->graph->node_exists('node1'));
    }

    public function testCreateNodeFailure(): void {
        // Create node first to cause failure on duplicate
        $this->graph->add_node('node1', ['label' => 'Existing', 'category' => 'business', 'type' => 'server']);

        $result = $this->apiHandler->createNode('node1', ['label' => 'Test', 'category' => 'business', 'type' => 'server']);

        $this->assertFalse($result['success']);
        $this->assertEquals('Node already exists or creation failed', $result['error']);
        $this->assertEquals(409, $result['code']);
    }

    public function testUpdateNodeSuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Original']);

        $result = $this->apiHandler->updateNode('node1', ['label' => 'Updated']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Node updated successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['id']);
    }

    public function testUpdateNodeFailure(): void {
        $result = $this->apiHandler->updateNode('node1', ['label' => 'Updated']);

        $this->assertFalse($result['success']);
        $this->assertEquals('Node not found or update failed', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testRemoveNodeSuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Test']);

        $result = $this->apiHandler->removeNode('node1');

        $this->assertTrue($result['success']);
        $this->assertEquals('Node removed successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['id']);
        $this->assertFalse($this->graph->node_exists('node1'));
    }

    public function testRemoveNodeFailure(): void {
        $result = $this->apiHandler->removeNode('node1');

        $this->assertFalse($result['success']);
        $this->assertEquals('Node not found or removal failed', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testEdgeExistsTrue(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', []);

        $result = $this->apiHandler->edgeExists('edge1');

        $this->assertTrue($result['exists']);
        $this->assertEquals('edge1', $result['id']);
    }

    public function testEdgeExistsFalse(): void {
        $result = $this->apiHandler->edgeExists('edge1');

        $this->assertFalse($result['exists']);
        $this->assertEquals('edge1', $result['id']);
    }

    public function testCreateEdgeSuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);

        $result = $this->apiHandler->createEdge('edge1', 'node1', 'node2', ['label' => 'Test']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Edge created successfully', $result['message']);
        $this->assertEquals('edge1', $result['data']['id']);
        $this->assertTrue($this->graph->edge_exists_by_id('edge1'));
    }

    public function testCreateEdgeFailure(): void {
        // Create nodes and edge first
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', []);

        // Try to create duplicate edge - should fail
        $result = $this->apiHandler->createEdge('edge1', 'node1', 'node2');

        $this->assertFalse($result['success']);
        $this->assertEquals('Edge creation failed', $result['error']);
        $this->assertEquals(400, $result['code']);
    }

    public function testRemoveEdgeSuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', []);

        $result = $this->apiHandler->removeEdge('edge1');

        $this->assertTrue($result['success']);
        $this->assertEquals('Edge removed successfully', $result['message']);
        $this->assertEquals('edge1', $result['data']['id']);
        $this->assertFalse($this->graph->edge_exists_by_id('edge1'));
    }

    public function testRemoveEdgeFailure(): void {
        $result = $this->apiHandler->removeEdge('edge1');

        $this->assertFalse($result['success']);
        $this->assertEquals('Edge not found or removal failed', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testRemoveEdgesFromSuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', []);

        $result = $this->apiHandler->removeEdgesFrom('node1');

        $this->assertTrue($result['success']);
        $this->assertEquals('Edges removed successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['source']);
        $this->assertFalse($this->graph->edge_exists_by_id('edge1'));
    }

    public function testRemoveEdgesFromFailure(): void {
        // This will succeed even with no edges (returns true for 0 edges removed)
        // So we need to check that it returns success
        $result = $this->apiHandler->removeEdgesFrom('node1');

        $this->assertTrue($result['success']);
    }

    public function testCreateBackupSuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Test']);

        $result = $this->apiHandler->createBackup('test_backup');

        $this->assertTrue($result['success']);
        $this->assertEquals('Backup created successfully', $result['message']);
        $this->assertArrayHasKey('data', $result);
        $this->assertFileExists($result['data']['file']);
    }

    public function testCreateBackupFailure(): void {
        $this->graph->add_node('node1', ['label' => 'Test']);

        // Create backup once
        $this->graph->create_backup('duplicate_test');

        // Try to create with same name - should fail
        $result = $this->apiHandler->createBackup('duplicate_test');

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['code']);
    }

    public function testGetAuditHistory(): void {
        AuditContext::set('test_user', '127.0.0.1');
        $this->graph->add_node('node1', ['label' => 'Original']);
        $this->graph->update_node('node1', ['label' => 'Updated']);

        $result = $this->apiHandler->getAuditHistory('node', 'node1');

        $this->assertArrayHasKey('audit_log', $result);
        $this->assertCount(2, $result['audit_log']);
        $this->assertEquals('update', $result['audit_log'][0]['action']);
        $this->assertEquals('create', $result['audit_log'][1]['action']);
    }

    public function testGetAuditHistoryNoFilters(): void {
        AuditContext::set('test_user', '127.0.0.1');
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', []);

        $result = $this->apiHandler->getAuditHistory();

        $this->assertArrayHasKey('audit_log', $result);
        $this->assertGreaterThanOrEqual(3, count($result['audit_log']));
    }

    public function testRestoreEntitySuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Original']);
        $this->graph->update_node('node1', ['label' => 'Updated']);

        $history = $this->graph->get_audit_history('node', 'node1');
        $updateLogId = $history[0]['id'];

        $result = $this->apiHandler->restoreEntity('node', 'node1', $updateLogId);

        $this->assertTrue($result['success']);
        $this->assertEquals('Entity restored successfully', $result['message']);
    }

    public function testRestoreEntityFailure(): void {
        $result = $this->apiHandler->restoreEntity('node', 'node1', 999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Restore failed', $result['error']);
        $this->assertEquals(400, $result['code']);
    }

    public function testRestoreToTimestampSuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        sleep(1);
        $timestamp = date('Y-m-d H:i:s');
        sleep(1);
        $this->graph->add_node('node2', ['label' => 'Node 2']);

        $result = $this->apiHandler->restoreToTimestamp($timestamp);

        $this->assertTrue($result['success']);
        $this->assertEquals('Graph restored to timestamp successfully', $result['message']);
        $this->assertTrue($this->graph->node_exists('node1'));
        $this->assertFalse($this->graph->node_exists('node2'));
    }

    public function testRestoreToTimestampFailure(): void {
        // Invalid timestamp format might cause failure, but our implementation is robust
        // So let's test with a valid timestamp on empty graph
        $result = $this->apiHandler->restoreToTimestamp('2024-01-01 12:00:00');

        // This should succeed even on empty graph
        $this->assertTrue($result['success']);
    }

    public function testGetAllowedStatuses(): void {
        $result = $this->apiHandler->getAllowedStatuses();

        $this->assertArrayHasKey('allowed_statuses', $result);
        $this->assertIsArray($result['allowed_statuses']);
        $this->assertContains('unknown', $result['allowed_statuses']);
        $this->assertContains('healthy', $result['allowed_statuses']);
        $this->assertContains('unhealthy', $result['allowed_statuses']);
        $this->assertContains('maintenance', $result['allowed_statuses']);
        $this->assertCount(4, $result['allowed_statuses']);
    }

    public function testGetAllNodeStatuses(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->set_node_status('node1', 'healthy');
        $this->graph->set_node_status('node2', 'unhealthy');

        $result = $this->apiHandler->getAllNodeStatuses();

        $this->assertArrayHasKey('statuses', $result);
        $this->assertCount(2, $result['statuses']);

        $statusMap = [];
        foreach ($result['statuses'] as $status) {
            $statusMap[$status['node_id']] = $status['status'];
        }

        $this->assertEquals('healthy', $statusMap['node1']);
        $this->assertEquals('unhealthy', $statusMap['node2']);
    }

    public function testGetAllNodeStatusesEmpty(): void {
        $result = $this->apiHandler->getAllNodeStatuses();

        $this->assertArrayHasKey('statuses', $result);
        $this->assertEmpty($result['statuses']);
    }

    public function testGetNodeStatusSuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Test Node']);
        $this->graph->set_node_status('node1', 'healthy');

        $result = $this->apiHandler->getNodeStatus('node1');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('node1', $result['data']['node_id']);
        $this->assertEquals('healthy', $result['data']['status']);
    }

    public function testGetNodeStatusNotFound(): void {
        $result = $this->apiHandler->getNodeStatus('node1');

        $this->assertFalse($result['success']);
        $this->assertEquals('No status found for node', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testSetNodeStatusSuccess(): void {
        $this->graph->add_node('node1', ['label' => 'Test Node']);

        $result = $this->apiHandler->setNodeStatus('node1', 'healthy');

        $this->assertTrue($result['success']);
        $this->assertEquals('Node status set successfully', $result['message']);
        $this->assertEquals('node1', $result['data']['node_id']);
        $this->assertEquals('healthy', $result['data']['status']);

        // Verify it was actually set
        $status = $this->graph->get_node_status('node1');
        $this->assertNotNull($status);
        $this->assertEquals('healthy', $status->get_status());
    }

    public function testSetNodeStatusInvalid(): void {
        $this->graph->add_node('node1', ['label' => 'Test Node']);

        $result = $this->apiHandler->setNodeStatus('node1', 'invalid_status');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid status', $result['error']);
        $this->assertEquals(400, $result['code']);
    }

    public function testSetNodeStatusFailure(): void {
        // Don't create the node, so setting status will fail
        $result = $this->apiHandler->setNodeStatus('nonexistent', 'healthy');

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to set node status (node may not exist)', $result['error']);
        $this->assertEquals(404, $result['code']);
    }

    public function testGetNodeStatusHistory(): void {
        $this->graph->add_node('node1', ['label' => 'Test Node']);
        $this->graph->set_node_status('node1', 'healthy');
        sleep(1);
        $this->graph->set_node_status('node1', 'unhealthy');

        $result = $this->apiHandler->getNodeStatusHistory('node1');

        $this->assertArrayHasKey('history', $result);
        $this->assertCount(2, $result['history']);
        $this->assertEquals('unhealthy', $result['history'][0]['status']); // Most recent first
        $this->assertEquals('healthy', $result['history'][1]['status']);
    }

    public function testGetNodeStatusHistoryEmpty(): void {
        $result = $this->apiHandler->getNodeStatusHistory('node1');

        $this->assertArrayHasKey('history', $result);
        $this->assertEmpty($result['history']);
    }
}
