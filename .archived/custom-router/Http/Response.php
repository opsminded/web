<?php declare(strict_types=1);

namespace Internet\Graph\Http;

/**
 * HTTP Response Value Object
 *
 * Immutable representation of an HTTP response.
 * Handles status codes, headers, and JSON encoding.
 */
class Response {
    /**
     * @param int $status HTTP status code
     * @param mixed $data Response data (will be JSON encoded)
     * @param array<string, string> $headers Additional headers
     */
    public function __construct(
        public readonly int $status,
        public readonly mixed $data,
        public readonly array $headers = []
    ) {}

    /**
     * Send the response to the client
     *
     * Sets HTTP status code, headers, and outputs JSON body.
     */
    public function send(): void {
        // Set HTTP status code
        http_response_code($this->status);

        // Set default content type if not provided
        if (!isset($this->headers['Content-Type'])) {
            header('Content-Type: application/json');
        }

        // Set additional headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Output JSON-encoded body
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Create new Response with additional headers
     *
     * Returns new instance (immutable).
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self {
        return new self(
            $this->status,
            $this->data,
            array_merge($this->headers, $headers)
        );
    }

    /**
     * Create a success response (200 OK)
     *
     * @param mixed $data Response data
     * @param string $message Success message
     */
    public static function success(mixed $data = null, string $message = 'Success'): self {
        $body = [
            'success' => true,
            'message' => $message
        ];

        if ($data !== null) {
            $body['data'] = $data;
        }

        return new self(200, $body);
    }

    /**
     * Create an error response
     *
     * @param int $status HTTP status code
     * @param string $error Error message
     * @param array|null $details Additional error details
     */
    public static function error(int $status, string $error, ?array $details = null): self {
        $body = ['error' => $error];

        if ($details !== null) {
            $body['details'] = $details;
        }

        return new self($status, $body);
    }

    /**
     * Create a 404 Not Found response
     *
     * @param string $path Request path
     * @param string $method HTTP method
     */
    public static function notFound(string $path, string $method): self {
        return self::error(404, 'Endpoint not found', [
            'path' => $path,
            'method' => $method
        ]);
    }

    /**
     * Create a 401 Unauthorized response
     *
     * @param string $message Error message
     */
    public static function unauthorized(string $message = 'Authentication required'): self {
        return self::error(401, $message);
    }

    /**
     * Create a 403 Forbidden response
     *
     * @param string $message Error message
     */
    public static function forbidden(string $message = 'Forbidden'): self {
        return self::error(403, $message);
    }

    /**
     * Create a 400 Bad Request response
     *
     * @param string $message Error message
     * @param array|null $details Validation errors
     */
    public static function badRequest(string $message, ?array $details = null): self {
        return self::error(400, $message, $details);
    }

    /**
     * Create a 500 Internal Server Error response
     *
     * @param string $message Error message
     */
    public static function serverError(string $message = 'Internal server error'): self {
        return self::error(500, $message);
    }
}
