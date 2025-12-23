<?php declare (strict_types=1);

namespace Internet\Graph;

class NodeStatus {
    private string $node_id;
    private string $status;
    private string $created_at;

    public function __construct(string $node_id, string $status, string $created_at) {
        $this->node_id = $node_id;
        $this->status = $status;
        $this->created_at = $created_at;
    }

    public function get_node_id(): string {
        return $this->node_id;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function get_created_at(): string {
        return $this->created_at;
    }

    public function to_array(): array {
        return [
            'node_id' => $this->node_id,
            'status' => $this->status,
            'created_at' => $this->created_at
        ];
    }
}
