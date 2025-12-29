<?php declare (strict_types=1);

namespace Internet\Graph;

use RuntimeException;
use Exception;

class Graph {
    private GraphDatabase $database;

    public function __construct(string $db_file) {
        $this->database = new GraphDatabase($db_file);
    }

    public function audit_log(
        string $entity_type,
        string $entity_id,
        string $action,
        ?array $old_data = null,
        ?array $new_data = null,
        ?string $user_id = null,
        ?string $ip_address = null
    ): bool {
        // Use global audit context if user_id/ip_address not provided
        if ($user_id === null) {
            $user_id = AuditContext::get_user();
        }
        if ($ip_address === null) {
            $ip_address = AuditContext::get_ip();
        }

        return $this->database->insertAuditLog(
            $entity_type,
            $entity_id,
            $action,
            $old_data,
            $new_data,
            $user_id,
            $ip_address
        );
    }

    public function get(): array {
        $nodesData = $this->database->fetchAllNodes();
        $edgesData = $this->database->fetchAllEdges();

        $nodes = [];
        foreach ($nodesData as $row) {
            $nodes[] = [
                'data' => json_decode($row['data'], true)
            ];
        }

        $edges = [];
        foreach ($edgesData as $row) {
            $edges[] = [
                'data' => json_decode($row['data'], true)
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    public function node_exists(string $id): bool {
        return $this->database->nodeExists($id);
    }

    public function add_node(string $id, array $data): bool {
        if ($this->database->nodeExists($id)) {
            return false;
        }

        $data['id'] = $id;
        $result = $this->database->insertNode($id, $data);

        if ($result) {
            $this->audit_log('node', $id, 'create', null, $data);
        }

        return $result;
    }

    public function update_node(string $id, array $data): bool {
        $old_data = $this->database->fetchNode($id);

        $data['id'] = $id;
        $rowCount = $this->database->updateNode($id, $data);

        if ($rowCount > 0) {
            $this->audit_log('node', $id, 'update', $old_data, $data);
            return true;
        }

        return false;
    }

    public function remove_node(string $id): bool {
        try {
            $this->database->beginTransaction();

            // Fetch node data before deleting
            $old_data = $this->database->fetchNode($id);
            if (!$old_data) {
                $this->database->rollBack();
                return false;
            }

            // Delete edges and get deleted edge data for audit log
            $deletedEdges = $this->database->deleteEdgesByNode($id);

            // Log each deleted edge
            foreach ($deletedEdges as $edge) {
                $this->audit_log('edge', $edge['id'], 'delete', $edge['data'], null);
            }

            // Delete the node
            [$rowCount, $_] = $this->database->deleteNode($id);

            if ($rowCount > 0) {
                $this->audit_log('node', $id, 'delete', $old_data, null);
            }

            $this->database->commit();
            return $rowCount > 0;
        // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            $this->database->rollBack();
            error_log("Graph remove node failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function edge_exists_by_id(string $id): bool {
        return $this->database->edgeExistsById($id);
    }

    public function edge_exists(string $source, string $target): bool {
        return $this->database->edgeExists($source, $target);
    }

    public function add_edge(string $id, string $source, string $target, array $data): bool {
        if ($this->database->edgeExistsById($id)) {
            return false;
        }

        $data['id'] = $id;
        $data['source'] = $source;
        $data['target'] = $target;

        $result = $this->database->insertEdge($id, $source, $target, $data);

        if ($result) {
            $this->audit_log('edge', $id, 'create', null, $data);
        }

        return $result;
    }

    public function remove_edge(string $id): bool {
        [$rowCount, $old_data] = $this->database->deleteEdge($id);

        if ($rowCount > 0) {
            $this->audit_log('edge', $id, 'delete', $old_data, null);
            return true;
        }

        return false;
    }

    public function remove_edges_from(string $source): bool {
        $edges = $this->database->deleteEdgesFrom($source);

        // Log each deleted edge
        foreach ($edges as $edge) {
            $this->audit_log('edge', $edge['id'], 'delete', $edge['data'], null);
        }

        return true;
    }

    public function create_backup(?string $backup_name = null): array {
        $result = $this->database->createBackup($backup_name);

        // Log the backup if successful
        if ($result['success']) {
            $this->audit_log('system', 'graph', 'backup', null, [
                'backup_file' => $result['file'],
                'backup_name' => $result['backup_name'],
                'file_size' => $result['file_size']
            ]);
        }

        return $result;
    }

    public function restore_to_timestamp(string $timestamp): bool {
        try {
            // Create backup before restoring
            $backup_name = 'pre_restore_timestamp_' . str_replace([' ', ':'], ['_', '-'], $timestamp);
            $backup_result = $this->database->createBackup($backup_name);
            if (!$backup_result['success']) {
                // @codeCoverageIgnoreStart
                error_log("Failed to create backup before restore: " . ($backup_result['error'] ?? 'Unknown error'));
                return false;
                // @codeCoverageIgnoreEnd
            }

            // Log the backup
            $this->audit_log('system', 'graph', 'backup', null, [
                'backup_file' => $backup_result['file'],
                'backup_name' => $backup_result['backup_name'],
                'file_size' => $backup_result['file_size']
            ]);

            // Perform the restore
            $result = $this->database->restoreToTimestamp($timestamp);

            // Log the restore operation if successful
            if ($result) {
                $logs = $this->database->fetchAuditLogsAfterTimestamp($timestamp);
                $this->audit_log('system', 'graph', 'restore_to_timestamp', null, [
                    'timestamp' => $timestamp,
                    'operations_reversed' => count($logs)
                ]);
            }

            return $result;
        // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            error_log("Graph restore to timestamp failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function get_audit_history(?string $entity_type = null, ?string $entity_id = null): array {
        return $this->database->fetchAuditHistory($entity_type, $entity_id);
    }

    public function restore_entity(string $entity_type, string $entity_id, int $audit_log_id): bool {
        try {
            // Create backup before restoring
            $backup_name = 'pre_restore_entity_' . $entity_type . '_' . $entity_id . '_' . date('Y-m-d_H-i-s') . '_' . rand(1000, 9999);
            $backup_result = $this->database->createBackup($backup_name);
            if (!$backup_result['success']) {
                // @codeCoverageIgnoreStart
                error_log("Failed to create backup before restore: " . ($backup_result['error'] ?? 'Unknown error'));
                return false;
                // @codeCoverageIgnoreEnd
            }

            // Log the backup
            $this->audit_log('system', 'graph', 'backup', null, [
                'backup_file' => $backup_result['file'],
                'backup_name' => $backup_result['backup_name'],
                'file_size' => $backup_result['file_size']
            ]);

            $this->database->beginTransaction();

            // Get the audit log entry
            $log = $this->database->fetchAuditLogById($audit_log_id, $entity_type, $entity_id);

            if (!$log) {
                $this->database->rollBack();
                return false;
            }

            $old_data = $log['old_data'];
            $action = $log['action'];

            // Reverse the operation
            if ($entity_type === 'node') {
                if ($action === 'delete' && $old_data !== null) {
                    // Restore deleted node
                    $this->database->insertNodeWithData($entity_id, $old_data);
                    $this->audit_log('node', $entity_id, 'restore', null, $old_data);
                } elseif ($action === 'create') {
                    // Remove created node
                    $this->database->deleteNode($entity_id);
                    $this->audit_log('node', $entity_id, 'restore_delete', $old_data, null);
                } elseif ($action === 'update' && $old_data !== null) {
                    // Restore to old data
                    $this->database->updateNode($entity_id, $old_data);
                    $this->audit_log('node', $entity_id, 'restore', null, $old_data);
                }
            } elseif ($entity_type === 'edge') {
                if ($action === 'delete' && $old_data !== null) {
                    // Restore deleted edge
                    $this->database->insertEdge($entity_id, $old_data['source'], $old_data['target'], $old_data);
                    $this->audit_log('edge', $entity_id, 'restore', null, $old_data);
                } elseif ($action === 'create') {
                    // Remove created edge
                    $this->database->deleteEdge($entity_id);
                    $this->audit_log('edge', $entity_id, 'restore_delete', $old_data, null);
                } elseif ($action === 'update' && $old_data !== null) {
                    // Restore to old data
                    $this->database->updateEdge($entity_id, $old_data['source'], $old_data['target'], $old_data);
                    $this->audit_log('edge', $entity_id, 'restore', null, $old_data);
                }
            }

            $this->database->commit();
            return true;
        // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            $this->database->rollBack();
            error_log("Graph restore entity failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function set_node_status(string $node_id, string $status): bool {
        if (!$this->database->nodeExists($node_id)) {
            return false;
        }

        $result = $this->database->insertNodeStatus($node_id, $status);

        if ($result) {
            $this->audit_log('node_status', $node_id, 'create', null, ['status' => $status]);
        }

        return $result;
    }

    public function get_node_status(string $node_id): ?NodeStatus {
        $row = $this->database->fetchLatestNodeStatus($node_id);

        if (!$row) {
            return null;
        }

        return new NodeStatus($row['node_id'], $row['status'], $row['created_at']);
    }

    public function get_node_status_history(string $node_id): array {
        $rows = $this->database->fetchNodeStatusHistory($node_id);

        $statuses = [];
        foreach ($rows as $row) {
            $statuses[] = new NodeStatus($row['node_id'], $row['status'], $row['created_at']);
        }

        return $statuses;
    }

    public function status(): array {
        $rows = $this->database->fetchAllLatestStatuses();

        $statuses = [];
        foreach ($rows as $row) {
            $statuses[] = new NodeStatus($row['node_id'], $row['status'], $row['created_at']);
        }

        return $statuses;
    }
}