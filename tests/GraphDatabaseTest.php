<?php declare(strict_types=1);

namespace Internet\Graph\Tests;

use PHPUnit\Framework\TestCase;
use Internet\Graph\GraphDatabase;

class GraphDatabaseTest extends TestCase {
    private string $testDbFile;
    private GraphDatabase $database;

    protected function setUp(): void {
        $this->testDbFile = './tmp/test_graphdb_' . uniqid() . '.db';
        $this->database = new GraphDatabase($this->testDbFile);
    }

    protected function tearDown(): void {
        // Clean up test database
        if (file_exists($this->testDbFile)) {
            unlink($this->testDbFile);
        }

        // Clean up backup directory if exists
        $backup_dir = dirname($this->testDbFile) . '/backups';
        if (is_dir($backup_dir)) {
            $files = glob($backup_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($backup_dir);
        }
    }

    // Node operations tests

    public function testNodeExists(): void {
        $this->assertFalse($this->database->nodeExists('node1'));

        $this->database->insertNode('node1', ['label' => 'Test']);
        $this->assertTrue($this->database->nodeExists('node1'));
    }

    public function testInsertNode(): void {
        $result = $this->database->insertNode('node1', ['label' => 'Test', 'id' => 'node1']);

        $this->assertTrue($result);
        $this->assertTrue($this->database->nodeExists('node1'));
    }

    public function testFetchNode(): void {
        $this->database->insertNode('node1', ['label' => 'Test', 'id' => 'node1']);

        $node = $this->database->fetchNode('node1');

        $this->assertNotNull($node);
        $this->assertEquals('Test', $node['label']);
        $this->assertEquals('node1', $node['id']);
    }

    public function testFetchNodeNonExistent(): void {
        $node = $this->database->fetchNode('nonexistent');

        $this->assertNull($node);
    }

    public function testUpdateNode(): void {
        $this->database->insertNode('node1', ['label' => 'Original', 'id' => 'node1']);

        $rowCount = $this->database->updateNode('node1', ['label' => 'Updated', 'id' => 'node1']);

        $this->assertEquals(1, $rowCount);

        $node = $this->database->fetchNode('node1');
        $this->assertEquals('Updated', $node['label']);
    }

    public function testUpdateNodeNonExistent(): void {
        $rowCount = $this->database->updateNode('nonexistent', ['label' => 'Test', 'id' => 'nonexistent']);

        $this->assertEquals(0, $rowCount);
    }

    public function testDeleteNode(): void {
        $this->database->insertNode('node1', ['label' => 'Test', 'id' => 'node1']);

        [$rowCount, $oldData] = $this->database->deleteNode('node1');

        $this->assertEquals(1, $rowCount);
        $this->assertNotNull($oldData);
        $this->assertEquals('Test', $oldData['label']);
        $this->assertFalse($this->database->nodeExists('node1'));
    }

    public function testDeleteNodeNonExistent(): void {
        [$rowCount, $oldData] = $this->database->deleteNode('nonexistent');

        $this->assertEquals(0, $rowCount);
        $this->assertNull($oldData);
    }

    public function testFetchAllNodes(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);

        $nodes = $this->database->fetchAllNodes();

        $this->assertCount(2, $nodes);
        $this->assertEquals('node1', $nodes[0]['id']);
        $this->assertEquals('node2', $nodes[1]['id']);
    }

    // Edge operations tests

    public function testEdgeExistsById(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);

        $this->assertFalse($this->database->edgeExistsById('edge1'));

        $this->database->insertEdge('edge1', 'node1', 'node2', ['label' => 'connects', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);
        $this->assertTrue($this->database->edgeExistsById('edge1'));
    }

    public function testEdgeExists(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);

        $this->assertFalse($this->database->edgeExists('node1', 'node2'));

        $this->database->insertEdge('edge1', 'node1', 'node2', ['label' => 'connects', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);
        $this->assertTrue($this->database->edgeExists('node1', 'node2'));
        $this->assertTrue($this->database->edgeExists('node2', 'node1')); // Should work in both directions
    }

    public function testInsertEdge(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);

        $result = $this->database->insertEdge('edge1', 'node1', 'node2', ['label' => 'connects', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);

        $this->assertTrue($result);
        $this->assertTrue($this->database->edgeExistsById('edge1'));
    }

    public function testDeleteEdge(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);
        $this->database->insertEdge('edge1', 'node1', 'node2', ['label' => 'connects', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);

        [$rowCount, $oldData] = $this->database->deleteEdge('edge1');

        $this->assertEquals(1, $rowCount);
        $this->assertNotNull($oldData);
        $this->assertEquals('connects', $oldData['label']);
        $this->assertFalse($this->database->edgeExistsById('edge1'));
    }

    public function testDeleteEdgesFrom(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);
        $this->database->insertNode('node3', ['label' => 'Test3', 'id' => 'node3']);
        $this->database->insertEdge('edge1', 'node1', 'node2', ['label' => 'connects1', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);
        $this->database->insertEdge('edge2', 'node1', 'node3', ['label' => 'connects2', 'id' => 'edge2', 'source' => 'node1', 'target' => 'node3']);

        $deletedEdges = $this->database->deleteEdgesFrom('node1');

        $this->assertCount(2, $deletedEdges);
        $this->assertEquals('edge1', $deletedEdges[0]['id']);
        $this->assertEquals('edge2', $deletedEdges[1]['id']);
        $this->assertFalse($this->database->edgeExistsById('edge1'));
        $this->assertFalse($this->database->edgeExistsById('edge2'));
    }

    public function testDeleteEdgesByNode(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);
        $this->database->insertNode('node3', ['label' => 'Test3', 'id' => 'node3']);
        $this->database->insertEdge('edge1', 'node1', 'node2', ['label' => 'connects1', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);
        $this->database->insertEdge('edge2', 'node2', 'node3', ['label' => 'connects2', 'id' => 'edge2', 'source' => 'node2', 'target' => 'node3']);

        $deletedEdges = $this->database->deleteEdgesByNode('node2');

        $this->assertCount(2, $deletedEdges);
        $this->assertFalse($this->database->edgeExistsById('edge1'));
        $this->assertFalse($this->database->edgeExistsById('edge2'));
    }

    public function testFetchAllEdges(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);
        $this->database->insertEdge('edge1', 'node1', 'node2', ['label' => 'connects', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);

        $edges = $this->database->fetchAllEdges();

        $this->assertCount(1, $edges);
        $this->assertEquals('edge1', $edges[0]['id']);
        $this->assertEquals('node1', $edges[0]['source']);
        $this->assertEquals('node2', $edges[0]['target']);
    }

    // Audit log tests

    public function testInsertAuditLog(): void {
        $result = $this->database->insertAuditLog(
            'node',
            'node1',
            'create',
            null,
            ['label' => 'Test'],
            'user123',
            '127.0.0.1'
        );

        $this->assertTrue($result);

        $history = $this->database->fetchAuditHistory('node', 'node1');
        $this->assertCount(1, $history);
        $this->assertEquals('create', $history[0]['action']);
        $this->assertEquals('user123', $history[0]['user_id']);
    }

    public function testFetchAuditHistory(): void {
        $this->database->insertAuditLog('node', 'node1', 'create', null, ['label' => 'Test'], null, null);
        $this->database->insertAuditLog('node', 'node1', 'update', ['label' => 'Test'], ['label' => 'Updated'], null, null);

        $history = $this->database->fetchAuditHistory('node', 'node1');

        $this->assertCount(2, $history);
        $this->assertEquals('update', $history[0]['action']); // Most recent first
        $this->assertEquals('create', $history[1]['action']);
    }

    public function testFetchAuditHistoryByType(): void {
        $this->database->insertAuditLog('node', 'node1', 'create', null, ['label' => 'Test'], null, null);
        $this->database->insertAuditLog('edge', 'edge1', 'create', null, ['label' => 'Connects'], null, null);

        $nodeHistory = $this->database->fetchAuditHistory('node');
        $this->assertCount(1, $nodeHistory);
        $this->assertEquals('node1', $nodeHistory[0]['entity_id']);

        $edgeHistory = $this->database->fetchAuditHistory('edge');
        $this->assertCount(1, $edgeHistory);
        $this->assertEquals('edge1', $edgeHistory[0]['entity_id']);
    }

    // Transaction tests

    public function testTransactionCommit(): void {
        $this->database->beginTransaction();

        $this->database->insertNode('node1', ['label' => 'Test', 'id' => 'node1']);
        $this->assertTrue($this->database->nodeExists('node1'));

        $this->database->commit();
        $this->assertTrue($this->database->nodeExists('node1'));
    }

    public function testTransactionRollback(): void {
        $this->database->beginTransaction();

        $this->database->insertNode('node1', ['label' => 'Test', 'id' => 'node1']);
        $this->assertTrue($this->database->nodeExists('node1'));

        $this->database->rollBack();

        // Node should not exist after rollback
        $this->assertFalse($this->database->nodeExists('node1'));
    }

    // Status tests

    public function testInsertNodeStatus(): void {
        $this->database->insertNode('node1', ['label' => 'Test', 'id' => 'node1']);

        $result = $this->database->insertNodeStatus('node1', 'running');

        $this->assertTrue($result);

        $status = $this->database->fetchLatestNodeStatus('node1');
        $this->assertNotNull($status);
        $this->assertEquals('running', $status['status']);
    }

    public function testFetchLatestNodeStatus(): void {
        $this->database->insertNode('node1', ['label' => 'Test', 'id' => 'node1']);
        $this->database->insertNodeStatus('node1', 'stopped');
        sleep(1); // 1 second delay to ensure different timestamps
        $this->database->insertNodeStatus('node1', 'running');

        $status = $this->database->fetchLatestNodeStatus('node1');

        $this->assertEquals('running', $status['status']); // Most recent status
    }

    public function testFetchNodeStatusHistory(): void {
        $this->database->insertNode('node1', ['label' => 'Test', 'id' => 'node1']);
        $this->database->insertNodeStatus('node1', 'stopped');
        sleep(1); // 1 second delay to ensure different timestamps
        $this->database->insertNodeStatus('node1', 'running');

        $history = $this->database->fetchNodeStatusHistory('node1');

        $this->assertCount(2, $history);
        $this->assertEquals('running', $history[0]['status']); // Most recent first
        $this->assertEquals('stopped', $history[1]['status']);
    }

    public function testFetchAllLatestStatuses(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);
        $this->database->insertNodeStatus('node1', 'running');
        $this->database->insertNodeStatus('node2', 'stopped');

        $statuses = $this->database->fetchAllLatestStatuses();

        $this->assertCount(2, $statuses);
    }

    // Backup support tests

    public function testGetDbFilePath(): void {
        $path = $this->database->getDbFilePath();

        $this->assertEquals($this->testDbFile, $path);
    }

    public function testCloseConnection(): void {
        $this->database->insertNode('node1', ['label' => 'Test', 'id' => 'node1']);

        $this->database->closeConnection();

        // After closing, we should still be able to perform operations
        // (connection will be reopened automatically)
        $this->assertTrue($this->database->nodeExists('node1'));
    }

    // Restore support methods tests

    public function testInsertNodeOrIgnore(): void {
        $this->database->insertNode('node1', ['label' => 'First', 'id' => 'node1']);

        // Try to insert duplicate - should be ignored
        $result = $this->database->insertNodeOrIgnore('node1', ['label' => 'Second', 'id' => 'node1']);

        $this->assertTrue($result);

        // Original data should still be there
        $node = $this->database->fetchNode('node1');
        $this->assertEquals('First', $node['label']);
    }

    public function testInsertEdgeOrIgnore(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);
        $this->database->insertEdge('edge1', 'node1', 'node2', ['label' => 'First', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);

        // Try to insert duplicate - should be ignored
        $result = $this->database->insertEdgeOrIgnore('edge1', 'node1', 'node2', ['label' => 'Second', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);

        $this->assertTrue($result);
    }

    public function testUpdateEdge(): void {
        $this->database->insertNode('node1', ['label' => 'Test1', 'id' => 'node1']);
        $this->database->insertNode('node2', ['label' => 'Test2', 'id' => 'node2']);
        $this->database->insertNode('node3', ['label' => 'Test3', 'id' => 'node3']);
        $this->database->insertEdge('edge1', 'node1', 'node2', ['label' => 'Original', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node2']);

        $rowCount = $this->database->updateEdge('edge1', 'node1', 'node3', ['label' => 'Updated', 'id' => 'edge1', 'source' => 'node1', 'target' => 'node3']);

        $this->assertEquals(1, $rowCount);
    }

    public function testFetchAuditLogById(): void {
        $this->database->insertAuditLog('node', 'node1', 'create', null, ['label' => 'Test'], 'user123', '127.0.0.1');

        // Get the audit log ID
        $history = $this->database->fetchAuditHistory('node', 'node1');
        $logId = $history[0]['id'];

        $log = $this->database->fetchAuditLogById($logId, 'node', 'node1');

        $this->assertNotNull($log);
        $this->assertEquals('create', $log['action']);
        $this->assertEquals('user123', $log['user_id']);
    }

    public function testFetchAuditLogsAfterTimestamp(): void {
        $this->database->insertAuditLog('node', 'node1', 'create', null, ['label' => 'Test'], null, null);

        sleep(1); // Ensure timestamp difference

        $timestamp = date('Y-m-d H:i:s');

        sleep(1);

        $this->database->insertAuditLog('node', 'node2', 'create', null, ['label' => 'Test2'], null, null);

        $logs = $this->database->fetchAuditLogsAfterTimestamp($timestamp);

        $this->assertCount(1, $logs);
        $this->assertEquals('node2', $logs[0]['entity_id']);
    }
}
