<?php declare(strict_types=1);

namespace Internet\Graph\Exception;

/**
 * Exception thrown when a node is not found
 */
class NodeNotFoundException extends GraphException
{
    public function __construct(string $nodeId, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Node not found: {$nodeId}",
            404,
            ['node_id' => $nodeId],
            $previous
        );
    }
}
