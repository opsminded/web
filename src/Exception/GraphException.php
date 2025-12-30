<?php declare(strict_types=1);

namespace Internet\Graph\Exception;

use RuntimeException;

/**
 * Base exception for all graph-related errors
 */
class GraphException extends RuntimeException
{
    protected int $httpStatusCode;
    protected array $context;

    /**
     * @param string $message Error message
     * @param int $httpStatusCode HTTP status code (default: 500)
     * @param array $context Additional context data
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatusCode = $httpStatusCode;
        $this->context = $context;
    }

    /**
     * Get HTTP status code for this exception
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get additional context data
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
