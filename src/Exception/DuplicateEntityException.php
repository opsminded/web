<?php declare(strict_types=1);

namespace Internet\Graph\Exception;

/**
 * Exception thrown when attempting to create an entity that already exists
 */
class DuplicateEntityException extends GraphException
{
    public function __construct(string $entityType, string $entityId, ?\Throwable $previous = null)
    {
        parent::__construct(
            "{$entityType} already exists: {$entityId}",
            409,
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            $previous
        );
    }
}
