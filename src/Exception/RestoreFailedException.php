<?php declare(strict_types=1);

namespace Internet\Graph\Exception;

/**
 * Exception thrown when a restore operation fails
 */
class RestoreFailedException extends GraphException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $context, $previous);
    }
}
