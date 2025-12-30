<?php declare(strict_types=1);

namespace Internet\Graph\Exception;

/**
 * Exception thrown when input validation fails
 */
class ValidationException extends GraphException
{
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $context, $previous);
    }
}
