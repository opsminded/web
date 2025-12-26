<?php declare (strict_types=1);

namespace Internet\Graph;

use PDO;
use PDOException;
use RuntimeException;
use Exception;

class Graph {
    private string $db_file;
    private ?PDO $db = null;
    
    public function __construct(string $db_file) {
        $this->db_file = $db_file;
        $this->initSchema();
    }

    private function initSchema(): void {
        $db = $this->get_db();

        $db->exec("
            CREATE TABLE IF NOT EXISTS nodes (
                id TEXT PRIMARY KEY,
                data TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS edges (
                id TEXT PRIMARY KEY,
                source TEXT NOT NULL,
                target TEXT NOT NULL,
                data TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (source) REFERENCES nodes(id) ON DELETE CASCADE,
                FOREIGN KEY (target) REFERENCES nodes(id) ON DELETE CASCADE
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT NOT NULL,
                entity_id TEXT NOT NULL,
                action TEXT NOT NULL,
                old_data TEXT,
                new_data TEXT,
                user_id TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_log(entity_type, entity_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at)");

        $db->exec("
            CREATE TABLE IF NOT EXISTS node_status (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                node_id TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (node_id) REFERENCES nodes(id) ON DELETE CASCADE
            )
        ");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_node_status_node_id ON node_status(node_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_node_status_created ON node_status(created_at)");
    }

    private function get_db(): PDO {
        if($this->db !== null) {
            return $this->db;
        }

        try {
            $this->db = new PDO('sqlite:' . $this->db_file);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
        // @codeCoverageIgnoreEnd

        return $this->db;
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
        try {
            // Use global audit context if user_id/ip_address not provided
            if ($user_id === null) {
                $user_id = AuditContext::get_user();
            }
            if ($ip_address === null) {
                $ip_address = AuditContext::get_ip();
            }

            $db = $this->get_db();
            $stmt = $db->prepare("
                INSERT INTO audit_log (entity_type, entity_id, action, old_data, new_data, user_id, ip_address)
                VALUES (:entity_type, :entity_id, :action, :old_data, :new_data, :user_id, :ip_address)
            ");
            $stmt->execute([
                ':entity_type' => $entity_type,
                ':entity_id' => $entity_id,
                ':action' => $action,
                ':old_data' => $old_data !== null ? json_encode($old_data, JSON_UNESCAPED_UNICODE) : null,
                ':new_data' => $new_data !== null ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null,
                ':user_id' => $user_id,
                ':ip_address' => $ip_address
            ]);
            return true;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Audit log failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function get(): array {
        try {
            $db = $this->get_db();

            // Get all nodes
            $stmt = $db->query("SELECT id, data FROM nodes ORDER BY created_at");
            $nodesData = $stmt->fetchAll();

            $nodes = [];
            foreach ($nodesData as $row) {
                $nodes[] = [
                    'data' => json_decode($row['data'], true)
                ];
            }

            // Get all edges
            $stmt = $db->query("SELECT id, source, target, data FROM edges ORDER BY created_at");
            $edgesData = $stmt->fetchAll();

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

        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph get failed: " . $e->getMessage());
            return ['nodes' => [], 'edges' => []];
        }
        // @codeCoverageIgnoreEnd
    }

    public function node_exists(string $id): bool {
        try {
            $db = $this->get_db();
            $stmt = $db->prepare("SELECT COUNT(*) FROM nodes WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetchColumn() > 0;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph node exists check failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function add_node(string $id, array $data): bool {
        if ($this->node_exists($id)) {
            return false;
        }

        try {
            $data['id'] = $id;
            $db = $this->get_db();
            $stmt = $db->prepare("INSERT INTO nodes (id, data) VALUES (:id, :data)");
            $stmt->execute([
                ':id' => $id,
                ':data' => json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);

            $this->audit_log('node', $id, 'create', null, $data);

            return true;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph add node failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function update_node(string $id, array $data): bool {
        try {
            $db = $this->get_db();

            $stmt = $db->prepare("SELECT data FROM nodes WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $old_row = $stmt->fetch();
            $old_data = $old_row ? json_decode($old_row['data'], true) : null;

            $data['id'] = $id;
            $stmt = $db->prepare("UPDATE nodes SET data = :data, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([
                ':id' => $id,
                ':data' => json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);

            if ($stmt->rowCount() > 0) {
                $this->audit_log('node', $id, 'update', $old_data, $data);
                return true;
            }

            return false;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph update node failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function remove_node(string $id): bool {
        try {
            $db = $this->get_db();
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT data FROM nodes WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $old_row = $stmt->fetch();
            $old_data = $old_row ? json_decode($old_row['data'], true) : null;

            // Fetch edges before deleting for audit log
            $stmt = $db->prepare("SELECT id, data FROM edges WHERE source = :id OR target = :id");
            $stmt->execute([':id' => $id]);
            $edges = $stmt->fetchAll();

            // Delete edges first (cascading should handle this, but being explicit)
            $stmt = $db->prepare("DELETE FROM edges WHERE source = :id OR target = :id");
            $stmt->execute([':id' => $id]);

            // Log each deleted edge
            foreach ($edges as $edge) {
                $edge_old_data = json_decode($edge['data'], true);
                $this->audit_log('edge', $edge['id'], 'delete', $edge_old_data, null);
            }

            // Delete the node
            $stmt = $db->prepare("DELETE FROM nodes WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                $this->audit_log('node', $id, 'delete', $old_data, null);
            }

            $db->commit();
            return $stmt->rowCount() > 0;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            error_log("Graph remove node failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function edge_exists_by_id(string $id): bool {
        try {
            $db = $this->get_db();
            $stmt = $db->prepare("SELECT COUNT(*) FROM edges WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetchColumn() > 0;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph edge exists by id check failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function edge_exists(string $source, string $target): bool {
        try {
            $db = $this->get_db();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM edges
                WHERE (source = :source AND target = :target)
                   OR (source = :target AND target = :source)
            ");
            $stmt->execute([
                ':source' => $source,
                ':target' => $target
            ]);
            return $stmt->fetchColumn() > 0;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph edge exists check failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function add_edge(string $id, string $source, string $target, array $data): bool {
        if ($this->edge_exists_by_id($id)) {
            return false;
        }
        
        try {
            $data['id'] = $id;
            $data['source'] = $source;
            $data['target'] = $target;

            $db = $this->get_db();
            $stmt = $db->prepare("INSERT INTO edges (id, source, target, data) VALUES (:id, :source, :target, :data)");
            $stmt->execute([
                ':id' => $id,
                ':source' => $source,
                ':target' => $target,
                ':data' => json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);

            $this->audit_log('edge', $id, 'create', null, $data);

            return true;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph add edge failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function remove_edge(string $id): bool {
        try {
            $db = $this->get_db();

            $stmt = $db->prepare("SELECT data FROM edges WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $old_row = $stmt->fetch();
            $old_data = $old_row ? json_decode($old_row['data'], true) : null;

            $stmt = $db->prepare("DELETE FROM edges WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                $this->audit_log('edge', $id, 'delete', $old_data, null);
                return true;
            }

            return false;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph remove edge failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function remove_edges_from(string $source): bool {
        try {
            $db = $this->get_db();

            // Fetch edges before deleting for audit log
            $stmt = $db->prepare("SELECT id, data FROM edges WHERE source = :source");
            $stmt->execute([':source' => $source]);
            $edges = $stmt->fetchAll();

            // Delete edges
            $stmt = $db->prepare("DELETE FROM edges WHERE source = :source");
            $stmt->execute([':source' => $source]);

            // Log each deleted edge
            foreach ($edges as $edge) {
                $old_data = json_decode($edge['data'], true);
                $this->audit_log('edge', $edge['id'], 'delete', $old_data, null);
            }

            return true;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph remove edges from failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function create_backup(?string $backup_name = null): array {
        $this->db = null; // Close existing connection before backup

        try {
            // Generate backup filename
            if ($backup_name === null) {
                $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '_' . rand(1000, 9999);
            }

            $backup_dir = dirname($this->db_file) . '/backups';
            if (!is_dir($backup_dir)) {
                // @codeCoverageIgnoreStart
                if (!mkdir($backup_dir, 0755, true)) {
                    throw new RuntimeException("Failed to create backup directory");
                }
                // @codeCoverageIgnoreEnd
            }

            $backup_file = $backup_dir . '/' . $backup_name . '.db';

            // Check if backup already exists
            if (file_exists($backup_file)) {
                return [
                    'success' => false,
                    'error' => 'Backup file already exists',
                    'file' => $backup_file
                ];
            }

            // Simple file copy
            // @codeCoverageIgnoreStart
            if (!copy($this->db_file, $backup_file)) {
                throw new RuntimeException("Failed to copy database file");
            }
            // @codeCoverageIgnoreEnd

            $file_size = filesize($backup_file);

            // Log the backup
            $this->audit_log('system', 'graph', 'backup', null, [
                'backup_file' => $backup_file,
                'backup_name' => $backup_name,
                'file_size' => $file_size
            ]);

            return [
                'success' => true,
                'file' => $backup_file,
                'backup_name' => $backup_name,
                'file_size' => $file_size
            ];
        // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            error_log("Graph create backup failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
        // @codeCoverageIgnoreEnd
    }

    public function get_audit_history(?string $entity_type = null, ?string $entity_id = null): array {
        try {
            $db = $this->get_db();

            $sql = "SELECT * FROM audit_log WHERE 1=1";
            $params = [];

            if ($entity_type !== null) {
                $sql .= " AND entity_type = :entity_type";
                $params[':entity_type'] = $entity_type;
            }

            if ($entity_id !== null) {
                $sql .= " AND entity_id = :entity_id";
                $params[':entity_id'] = $entity_id;
            }

            $sql .= " ORDER BY created_at DESC, id DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll();

            // Decode JSON data
            foreach ($logs as &$log) {
                $log['old_data'] = $log['old_data'] !== null ? json_decode($log['old_data'], true) : null;
                $log['new_data'] = $log['new_data'] !== null ? json_decode($log['new_data'], true) : null;
            }

            return $logs;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph get audit history failed: " . $e->getMessage());
            return [];
        }
        // @codeCoverageIgnoreEnd
    }

    public function restore_entity(string $entity_type, string $entity_id, int $audit_log_id): bool {
        try {
            // Create backup before restoring
            $backup_name = 'pre_restore_entity_' . $entity_type . '_' . $entity_id . '_' . date('Y-m-d_H-i-s') . '_' . rand(1000, 9999);
            $backup_result = $this->create_backup($backup_name);
            if (!$backup_result['success']) {
                // @codeCoverageIgnoreStart
                error_log("Failed to create backup before restore: " . ($backup_result['error'] ?? 'Unknown error'));
                return false;
                // @codeCoverageIgnoreEnd
            }

            $db = $this->get_db();
            $db->beginTransaction();

            // Get the audit log entry
            $stmt = $db->prepare("SELECT * FROM audit_log WHERE id = :id AND entity_type = :entity_type AND entity_id = :entity_id");
            $stmt->execute([
                ':id' => $audit_log_id,
                ':entity_type' => $entity_type,
                ':entity_id' => $entity_id
            ]);
            $log = $stmt->fetch();

            if (!$log) {
                $db->rollBack();
                return false;
            }

            $old_data = $log['old_data'] !== null ? json_decode($log['old_data'], true) : null;
            $action = $log['action'];

            // Reverse the operation
            if ($entity_type === 'node') {
                if ($action === 'delete' && $old_data !== null) {
                    // Restore deleted node
                    $stmt = $db->prepare("INSERT INTO nodes (id, data) VALUES (:id, :data)");
                    $stmt->execute([
                        ':id' => $entity_id,
                        ':data' => json_encode($old_data, JSON_UNESCAPED_UNICODE)
                    ]);
                    $this->audit_log('node', $entity_id, 'restore', null, $old_data);
                } elseif ($action === 'create') {
                    // Remove created node
                    $stmt = $db->prepare("DELETE FROM nodes WHERE id = :id");
                    $stmt->execute([':id' => $entity_id]);
                    $this->audit_log('node', $entity_id, 'restore_delete', $old_data, null);
                } elseif ($action === 'update' && $old_data !== null) {
                    // Restore to old data
                    $stmt = $db->prepare("UPDATE nodes SET data = :data, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $stmt->execute([
                        ':id' => $entity_id,
                        ':data' => json_encode($old_data, JSON_UNESCAPED_UNICODE)
                    ]);
                    $this->audit_log('node', $entity_id, 'restore', null, $old_data);
                }
            } elseif ($entity_type === 'edge') {
                if ($action === 'delete' && $old_data !== null) {
                    // Restore deleted edge
                    $stmt = $db->prepare("INSERT INTO edges (id, source, target, data) VALUES (:id, :source, :target, :data)");
                    $stmt->execute([
                        ':id' => $entity_id,
                        ':source' => $old_data['source'],
                        ':target' => $old_data['target'],
                        ':data' => json_encode($old_data, JSON_UNESCAPED_UNICODE)
                    ]);
                    $this->audit_log('edge', $entity_id, 'restore', null, $old_data);
                } elseif ($action === 'create') {
                    // Remove created edge
                    $stmt = $db->prepare("DELETE FROM edges WHERE id = :id");
                    $stmt->execute([':id' => $entity_id]);
                    $this->audit_log('edge', $entity_id, 'restore_delete', $old_data, null);
                } elseif ($action === 'update' && $old_data !== null) {
                    // Restore to old data
                    $stmt = $db->prepare("UPDATE edges SET source = :source, target = :target, data = :data, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $stmt->execute([
                        ':id' => $entity_id,
                        ':source' => $old_data['source'],
                        ':target' => $old_data['target'],
                        ':data' => json_encode($old_data, JSON_UNESCAPED_UNICODE)
                    ]);
                    $this->audit_log('edge', $entity_id, 'restore', null, $old_data);
                }
            }

            $db->commit();
            return true;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            error_log("Graph restore entity failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function restore_to_timestamp(string $timestamp): bool {
        try {
            // Create backup before restoring
            $backup_name = 'pre_restore_timestamp_' . str_replace([' ', ':'], ['_', '-'], $timestamp);
            $backup_result = $this->create_backup($backup_name);
            if (!$backup_result['success']) {
                // @codeCoverageIgnoreStart
                error_log("Failed to create backup before restore: " . ($backup_result['error'] ?? 'Unknown error'));
                return false;
                // @codeCoverageIgnoreEnd
            }

            $db = $this->get_db();
            $db->beginTransaction();

            // Get all audit logs after the specified timestamp in reverse order
            $stmt = $db->prepare("
                SELECT * FROM audit_log
                WHERE created_at > :timestamp
                ORDER BY created_at DESC, id DESC
            ");
            $stmt->execute([':timestamp' => $timestamp]);
            $logs = $stmt->fetchAll();

            // Reverse each operation
            foreach ($logs as $log) {
                $entity_type = $log['entity_type'];
                $entity_id = $log['entity_id'];
                $action = $log['action'];
                $old_data = $log['old_data'] !== null ? json_decode($log['old_data'], true) : null;

                // Skip restore actions to avoid infinite loops
                if ($action === 'restore' || $action === 'restore_delete') {
                    continue;
                }

                if ($entity_type === 'node') {
                    if ($action === 'delete' && $old_data !== null) {
                        // Restore deleted node
                        $stmt = $db->prepare("INSERT OR IGNORE INTO nodes (id, data) VALUES (:id, :data)");
                        $stmt->execute([
                            ':id' => $entity_id,
                            ':data' => json_encode($old_data, JSON_UNESCAPED_UNICODE)
                        ]);
                    } elseif ($action === 'create') {
                        // Remove created node (and its edges)
                        $stmt = $db->prepare("DELETE FROM edges WHERE source = :id OR target = :id");
                        $stmt->execute([':id' => $entity_id]);
                        $stmt = $db->prepare("DELETE FROM nodes WHERE id = :id");
                        $stmt->execute([':id' => $entity_id]);
                    } elseif ($action === 'update' && $old_data !== null) {
                        // Restore to old data
                        $stmt = $db->prepare("UPDATE nodes SET data = :data, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $stmt->execute([
                            ':id' => $entity_id,
                            ':data' => json_encode($old_data, JSON_UNESCAPED_UNICODE)
                        ]);
                    }
                } elseif ($entity_type === 'edge') {
                    if ($action === 'delete' && $old_data !== null) {
                        // Restore deleted edge (only if both nodes exist)
                        $stmt = $db->prepare("SELECT COUNT(*) FROM nodes WHERE id = :source");
                        $stmt->execute([':source' => $old_data['source']]);
                        $source_exists = $stmt->fetchColumn() > 0;

                        $stmt = $db->prepare("SELECT COUNT(*) FROM nodes WHERE id = :target");
                        $stmt->execute([':target' => $old_data['target']]);
                        $target_exists = $stmt->fetchColumn() > 0;

                        if ($source_exists && $target_exists) {
                            $stmt = $db->prepare("INSERT OR IGNORE INTO edges (id, source, target, data) VALUES (:id, :source, :target, :data)");
                            $stmt->execute([
                                ':id' => $entity_id,
                                ':source' => $old_data['source'],
                                ':target' => $old_data['target'],
                                ':data' => json_encode($old_data, JSON_UNESCAPED_UNICODE)
                            ]);
                        }
                    } elseif ($action === 'create') {
                        // Remove created edge
                        $stmt = $db->prepare("DELETE FROM edges WHERE id = :id");
                        $stmt->execute([':id' => $entity_id]);
                    } elseif ($action === 'update' && $old_data !== null) {
                        // Restore to old data
                        $stmt = $db->prepare("UPDATE edges SET source = :source, target = :target, data = :data, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $stmt->execute([
                            ':id' => $entity_id,
                            ':source' => $old_data['source'],
                            ':target' => $old_data['target'],
                            ':data' => json_encode($old_data, JSON_UNESCAPED_UNICODE)
                        ]);
                    }
                }
            }

            // Log the restore operation
            $this->audit_log('system', 'graph', 'restore_to_timestamp', null, ['timestamp' => $timestamp, 'operations_reversed' => count($logs)]);

            $db->commit();
            return true;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            error_log("Graph restore to timestamp failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function set_node_status(string $node_id, string $status): bool {
        if (!$this->node_exists($node_id)) {
            return false;
        }

        try {
            $db = $this->get_db();
            $stmt = $db->prepare("
                INSERT INTO node_status (node_id, status)
                VALUES (:node_id, :status)
            ");
            $stmt->execute([
                ':node_id' => $node_id,
                ':status' => $status
            ]);

            $this->audit_log('node_status', $node_id, 'create', null, ['status' => $status]);

            return true;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph set node status failed: " . $e->getMessage());
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function get_node_status(string $node_id): ?NodeStatus {
        try {
            $db = $this->get_db();
            $stmt = $db->prepare("
                SELECT node_id, status, created_at
                FROM node_status
                WHERE node_id = :node_id
                AND created_at = (
                    SELECT MAX(created_at)
                    FROM node_status
                    WHERE node_id = :node_id
                )
                LIMIT 1
            ");
            $stmt->execute([':node_id' => $node_id]);
            $row = $stmt->fetch();

            if (!$row) {
                return null;
            }

            return new NodeStatus($row['node_id'], $row['status'], $row['created_at']);
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph get node status failed: " . $e->getMessage());
            return null;
        }
        // @codeCoverageIgnoreEnd
    }

    public function get_node_status_history(string $node_id): array {
        try {
            $db = $this->get_db();
            $stmt = $db->prepare("
                SELECT node_id, status, created_at
                FROM node_status
                WHERE node_id = :node_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':node_id' => $node_id]);
            $rows = $stmt->fetchAll();

            $statuses = [];
            foreach ($rows as $row) {
                $statuses[] = new NodeStatus($row['node_id'], $row['status'], $row['created_at']);
            }

            return $statuses;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph get node status history failed: " . $e->getMessage());
            return [];
        }
        // @codeCoverageIgnoreEnd
    }

    public function status(): array {
        try {
            $db = $this->get_db();
            $stmt = $db->query("
                SELECT ns.node_id, ns.status, ns.created_at
                FROM node_status ns
                INNER JOIN (
                    SELECT node_id, MAX(created_at) as max_created_at
                    FROM node_status
                    GROUP BY node_id
                ) latest ON ns.node_id = latest.node_id AND ns.created_at = latest.max_created_at
                INNER JOIN nodes n ON ns.node_id = n.id
                ORDER BY ns.node_id
            ");
            $rows = $stmt->fetchAll();

            $statuses = [];
            foreach ($rows as $row) {
                $statuses[] = new NodeStatus($row['node_id'], $row['status'], $row['created_at']);
            }

            return $statuses;
        // @codeCoverageIgnoreStart
        } catch (PDOException $e) {
            error_log("Graph get all statuses failed: " . $e->getMessage());
            return [];
        }
        // @codeCoverageIgnoreEnd
    }
}