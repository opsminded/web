<?php declare(strict_types=1);

namespace Internet\Graph;

/**
 * API Handler for Graph operations
 * Contains business logic for API endpoints
 */
class ApiHandler {
    private Graph $graph;

    // Allowed node statuses
    private const ALLOWED_STATUSES = ['unknown', 'healthy', 'unhealthy', 'maintenance'];

    public function __construct(Graph $graph) {
        $this->graph = $graph;
    }

    /**
     * Get the entire graph
     */
    public function getGraph(): array {
        return $this->graph->get();
    }

    /**
     * Check if a node exists
     */
    public function nodeExists(string $id): array {
        $exists = $this->graph->node_exists($id);
        return ['exists' => $exists, 'id' => $id];
    }

    /**
     * Create a new node
     */
    public function createNode(string $id, array $data): array {
        if ($this->graph->add_node($id, $data)) {
            return [
                'success' => true,
                'message' => 'Node created successfully',
                'data' => ['id' => $id]
            ];
        }
        return [
            'success' => false,
            'error' => 'Node already exists or creation failed',
            'code' => 409
        ];
    }

    /**
     * Update an existing node
     */
    public function updateNode(string $id, array $data): array {
        if ($this->graph->update_node($id, $data)) {
            return [
                'success' => true,
                'message' => 'Node updated successfully',
                'data' => ['id' => $id]
            ];
        }
        return [
            'success' => false,
            'error' => 'Node not found or update failed',
            'code' => 404
        ];
    }

    /**
     * Remove a node
     */
    public function removeNode(string $id): array {
        if ($this->graph->remove_node($id)) {
            return [
                'success' => true,
                'message' => 'Node removed successfully',
                'data' => ['id' => $id]
            ];
        }
        return [
            'success' => false,
            'error' => 'Node not found or removal failed',
            'code' => 404
        ];
    }

    /**
     * Check if an edge exists by ID
     */
    public function edgeExists(string $id): array {
        $exists = $this->graph->edge_exists_by_id($id);
        return ['exists' => $exists, 'id' => $id];
    }

    /**
     * Create a new edge
     */
    public function createEdge(string $id, string $source, string $target, array $data = []): array {
        if ($this->graph->add_edge($id, $source, $target, $data)) {
            return [
                'success' => true,
                'message' => 'Edge created successfully',
                'data' => ['id' => $id]
            ];
        }
        return [
            'success' => false,
            'error' => 'Edge creation failed',
            'code' => 400
        ];
    }

    /**
     * Remove an edge
     */
    public function removeEdge(string $id): array {
        if ($this->graph->remove_edge($id)) {
            return [
                'success' => true,
                'message' => 'Edge removed successfully',
                'data' => ['id' => $id]
            ];
        }
        return [
            'success' => false,
            'error' => 'Edge not found or removal failed',
            'code' => 404
        ];
    }

    /**
     * Remove all edges from a source node
     */
    public function removeEdgesFrom(string $source): array {
        if ($this->graph->remove_edges_from($source)) {
            return [
                'success' => true,
                'message' => 'Edges removed successfully',
                'data' => ['source' => $source]
            ];
        }
        return [
            'success' => false,
            'error' => 'Failed to remove edges',
            'code' => 400
        ];
    }

    /**
     * Create a backup
     */
    public function createBackup(?string $backup_name = null): array {
        $result = $this->graph->create_backup($backup_name);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'data' => $result
            ];
        }
        return [
            'success' => false,
            'error' => 'Backup failed',
            'details' => $result,
            'code' => 500
        ];
    }

    /**
     * Get audit history
     */
    public function getAuditHistory(?string $entity_type = null, ?string $entity_id = null): array {
        $history = $this->graph->get_audit_history($entity_type, $entity_id);
        return ['audit_log' => $history];
    }

    /**
     * Restore a specific entity
     */
    public function restoreEntity(string $entity_type, string $entity_id, int $audit_log_id): array {
        if ($this->graph->restore_entity($entity_type, $entity_id, $audit_log_id)) {
            return [
                'success' => true,
                'message' => 'Entity restored successfully'
            ];
        }
        return [
            'success' => false,
            'error' => 'Restore failed',
            'code' => 400
        ];
    }

    /**
     * Restore graph to a specific timestamp
     */
    public function restoreToTimestamp(string $timestamp): array {
        if ($this->graph->restore_to_timestamp($timestamp)) {
            return [
                'success' => true,
                'message' => 'Graph restored to timestamp successfully'
            ];
        }
        return [
            'success' => false,
            'error' => 'Restore failed',
            'code' => 400
        ];
    }

    /**
     * Get allowed status values
     */
    public function getAllowedStatuses(): array {
        return ['allowed_statuses' => self::ALLOWED_STATUSES];
    }

    /**
     * Get status of all nodes
     */
    public function getAllNodeStatuses(): array {
        $statuses = $this->graph->status();
        $result = [];
        foreach ($statuses as $status) {
            $result[] = $status->to_array();
        }
        return ['statuses' => $result];
    }

    /**
     * Get status of a specific node
     */
    public function getNodeStatus(string $node_id): array {
        $status = $this->graph->get_node_status($node_id);
        if ($status !== null) {
            return [
                'success' => true,
                'data' => $status->to_array()
            ];
        }
        return [
            'success' => false,
            'error' => 'No status found for node',
            'code' => 404
        ];
    }

    /**
     * Set status of a node
     */
    public function setNodeStatus(string $node_id, string $status): array {
        // Validate status
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return [
                'success' => false,
                'error' => 'Invalid status. Allowed values: ' . implode(', ', self::ALLOWED_STATUSES),
                'code' => 400
            ];
        }

        if ($this->graph->set_node_status($node_id, $status)) {
            return [
                'success' => true,
                'message' => 'Node status set successfully',
                'data' => ['node_id' => $node_id, 'status' => $status]
            ];
        }
        return [
            'success' => false,
            'error' => 'Failed to set node status (node may not exist)',
            'code' => 404
        ];
    }

    /**
     * Get status history of a node
     */
    public function getNodeStatusHistory(string $node_id): array {
        $history = $this->graph->get_node_status_history($node_id);
        $result = [];
        foreach ($history as $status) {
            $result[] = $status->to_array();
        }
        return ['history' => $result];
    }
}
