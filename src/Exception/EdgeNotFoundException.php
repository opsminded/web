<?php declare(strict_types=1);

namespace Internet\Graph\Exception;

/**
 * Exception thrown when an edge is not found
 */
class EdgeNotFoundException extends GraphException
{
    public function __construct(string $edgeId, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Edge not found: {$edgeId}",
            404,
            ['edge_id' => $edgeId],
            $previous
        );
    }
}
