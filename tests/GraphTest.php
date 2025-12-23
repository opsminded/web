<?php declare(strict_types=1);

namespace Internet\Graph\Tests;

use Internet\Graph\Graph;
use Internet\Graph\AuditContext;
use PHPUnit\Framework\TestCase;

class GraphTest extends TestCase {
    private string $testDbFile;
    private Graph $graph;

    protected function setUp(): void {
        parent::setUp();

        $this->testDbFile = './tmp/test_graph_' . uniqid() . '.db';
        $this->graph = new Graph($this->testDbFile);
        AuditContext::clear();
    }

    protected function tearDown(): void {
        parent::tearDown();
        
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

    public function testAddNode(): void {
        $result = $this->graph->add_node('node1', ['label' => 'Test Node']);

        $this->assertTrue($result);
        $this->assertTrue($this->graph->node_exists('node1'));
    }

    public function testAddNodeDuplicate(): void {
        $this->graph->add_node('node1', ['label' => 'Test Node']);
        $result = $this->graph->add_node('node1', ['label' => 'Duplicate']);

        $this->assertFalse($result);
    }

    public function testNodeExists(): void {
        $this->assertFalse($this->graph->node_exists('nonexistent'));

        $this->graph->add_node('existing', ['label' => 'Existing Node']);

        $this->assertTrue($this->graph->node_exists('existing'));
    }

    public function testUpdateNode(): void {
        $this->graph->add_node('node1', ['label' => 'Original']);

        $result = $this->graph->update_node('node1', ['label' => 'Updated']);

        $this->assertTrue($result);
    }

    public function testUpdateNonexistentNode(): void {
        $result = $this->graph->update_node('nonexistent', ['label' => 'Data']);

        $this->assertFalse($result);
    }

    public function testRemoveNode(): void {
        $this->graph->add_node('node1', ['label' => 'To Remove']);

        $result = $this->graph->remove_node('node1');

        $this->assertTrue($result);
        $this->assertFalse($this->graph->node_exists('node1'));
    }

    public function testRemoveNonexistentNode(): void {
        $result = $this->graph->remove_node('nonexistent');

        $this->assertFalse($result);
    }

    public function testRemoveNodeWithEdges(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', ['label' => 'Edge']);

        $result = $this->graph->remove_node('node1');

        $this->assertTrue($result);
        $this->assertFalse($this->graph->edge_exists_by_id('edge1'));
    }

    public function testAddEdge(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);

        $result = $this->graph->add_edge('edge1', 'node1', 'node2', ['label' => 'Test Edge']);

        $this->assertTrue($result);
        $this->assertTrue($this->graph->edge_exists_by_id('edge1'));
    }

    public function testEdgeExistsById(): void {
        $this->assertFalse($this->graph->edge_exists_by_id('nonexistent'));

        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', []);

        $this->assertTrue($this->graph->edge_exists_by_id('edge1'));
    }

    public function testEdgeExists(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);

        $this->assertFalse($this->graph->edge_exists('node1', 'node2'));

        $this->graph->add_edge('edge1', 'node1', 'node2', []);

        $this->assertTrue($this->graph->edge_exists('node1', 'node2'));
        $this->assertTrue($this->graph->edge_exists('node2', 'node1'));
    }

    public function testRemoveEdge(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', []);

        $result = $this->graph->remove_edge('edge1');

        $this->assertTrue($result);
        $this->assertFalse($this->graph->edge_exists_by_id('edge1'));
    }

    public function testRemoveNonexistentEdge(): void {
        $result = $this->graph->remove_edge('nonexistent');

        $this->assertFalse($result);
    }

    public function testRemoveEdgesFrom(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_node('node3', ['label' => 'Node 3']);
        $this->graph->add_edge('edge1', 'node1', 'node2', []);
        $this->graph->add_edge('edge2', 'node1', 'node3', []);

        $result = $this->graph->remove_edges_from('node1');

        $this->assertTrue($result);
        $this->assertFalse($this->graph->edge_exists_by_id('edge1'));
        $this->assertFalse($this->graph->edge_exists_by_id('edge2'));
    }

    public function testGet(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', ['label' => 'Edge 1']);

        $result = $this->graph->get();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('edges', $result);
        $this->assertCount(2, $result['nodes']);
        $this->assertCount(1, $result['edges']);
    }

    public function testAuditLog(): void {
        AuditContext::set('user123', '192.168.1.1');

        $result = $this->graph->audit_log(
            'test_entity',
            'test_id',
            'test_action',
            ['old' => 'data'],
            ['new' => 'data']
        );

        $this->assertTrue($result);

        $history = $this->graph->get_audit_history('test_entity', 'test_id');
        $this->assertCount(1, $history);
        $this->assertEquals('test_action', $history[0]['action']);
        $this->assertEquals('user123', $history[0]['user_id']);
        $this->assertEquals('192.168.1.1', $history[0]['ip_address']);
    }

    public function testAuditLogUsesContext(): void {
        AuditContext::set('context_user', '10.0.0.1');

        $this->graph->add_node('node1', ['label' => 'Test']);

        $history = $this->graph->get_audit_history('node', 'node1');
        $this->assertCount(1, $history);
        $this->assertEquals('context_user', $history[0]['user_id']);
        $this->assertEquals('10.0.0.1', $history[0]['ip_address']);
    }

    public function testGetAuditHistory(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->update_node('node1', ['label' => 'Updated']);
        $this->graph->remove_node('node1');

        $history = $this->graph->get_audit_history('node', 'node1');

        $this->assertCount(3, $history);
        $this->assertEquals('delete', $history[0]['action']);
        $this->assertEquals('update', $history[1]['action']);
        $this->assertEquals('create', $history[2]['action']);
    }

    public function testGetAuditHistoryFilterByType(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', []);

        $nodeHistory = $this->graph->get_audit_history('node');
        $edgeHistory = $this->graph->get_audit_history('edge');

        $this->assertCount(2, $nodeHistory);
        $this->assertCount(1, $edgeHistory);
    }

    public function testCreateBackup(): void {
        $this->graph->add_node('node1', ['label' => 'Test']);

        $result = $this->graph->create_backup('test_backup');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('file', $result);
        $this->assertFileExists($result['file']);
    }

    public function testCreateBackupAutoName(): void {
        $result = $this->graph->create_backup();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('backup_', $result['backup_name']);
    }

    public function testCreateBackupDuplicate(): void {
        $this->graph->create_backup('duplicate_test');
        $result = $this->graph->create_backup('duplicate_test');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['error']);
    }

    public function testRestoreEntity(): void {
        $this->graph->add_node('node1', ['label' => 'Original']);
        $this->graph->update_node('node1', ['label' => 'Updated']);

        $history = $this->graph->get_audit_history('node', 'node1');
        $updateLogId = $history[0]['id'];

        $result = $this->graph->restore_entity('node', 'node1', $updateLogId);

        $this->assertTrue($result);

        $graphData = $this->graph->get();
        $nodeData = $graphData['nodes'][0]['data'];
        $this->assertEquals('Original', $nodeData['label']);
    }

    public function testRestoreEntityDelete(): void {
        $this->graph->add_node('node1', ['label' => 'Test']);
        $this->graph->remove_node('node1');

        $history = $this->graph->get_audit_history('node', 'node1');
        $deleteLogId = $history[0]['id'];
        print("Delete Log ID: " . $deleteLogId);
        $result = $this->graph->restore_entity('node', 'node1', $deleteLogId);

        $this->assertTrue($result);
        $this->assertTrue($this->graph->node_exists('node1'));
    }

    public function testRestoreEntityCreate(): void {
        $this->graph->add_node('node1', ['label' => 'Test']);

        $history = $this->graph->get_audit_history('node', 'node1');
        $createLogId = $history[0]['id'];

        $result = $this->graph->restore_entity('node', 'node1', $createLogId);

        $this->assertTrue($result);
        $this->assertFalse($this->graph->node_exists('node1'));
    }

    public function testRestoreToTimestamp(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        sleep(1);
        $timestamp = date('Y-m-d H:i:s');
        sleep(1);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->update_node('node1', ['label' => 'Updated']);

        $result = $this->graph->restore_to_timestamp($timestamp);

        $this->assertTrue($result);

        $this->assertTrue($this->graph->node_exists('node1'));
        $this->assertFalse($this->graph->node_exists('node2'));
    }

    public function testEdgeDataIncludesSourceAndTarget(): void {
        $this->graph->add_node('node1', ['label' => 'Node 1']);
        $this->graph->add_node('node2', ['label' => 'Node 2']);
        $this->graph->add_edge('edge1', 'node1', 'node2', ['type' => 'connection']);

        $graphData = $this->graph->get();
        $edgeData = $graphData['edges'][0]['data'];

        $this->assertEquals('edge1', $edgeData['id']);
        $this->assertEquals('node1', $edgeData['source']);
        $this->assertEquals('node2', $edgeData['target']);
        $this->assertEquals('connection', $edgeData['type']);
    }

    public function testNodeDataIncludesId(): void {
        $this->graph->add_node('node1', ['label' => 'Test Node', 'color' => 'red']);

        $graphData = $this->graph->get();
        $nodeData = $graphData['nodes'][0]['data'];

        $this->assertEquals('node1', $nodeData['id']);
        $this->assertEquals('Test Node', $nodeData['label']);
        $this->assertEquals('red', $nodeData['color']);
    }
}
