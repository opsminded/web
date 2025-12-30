<?php declare(strict_types=1);

namespace Internet\Graph\Http;

/**
 * HTTP Request Value Object
 *
 * Immutable representation of an HTTP request.
 * Parses and normalizes request data from PHP globals.
 */
class Request {
    /**
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $path Request path without query string
     * @param array<int, string> $segments Path segments (filtered, indexed)
     * @param array<string, string> $headers HTTP headers
     * @param array|null $body Decoded JSON body (null if no body or invalid JSON)
     * @param array<string, string> $query Query parameters
     * @param array<string, string> $params Route parameters (injected after matching)
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $segments,
        public readonly array $headers,
        public readonly ?array $body,
        public readonly array $query,
        public readonly array $params = []
    ) {}

    /**
     * Create Request from PHP globals ($_SERVER, $_GET, php://input)
     */
    public static function fromGlobals(): self {
        // Parse HTTP method
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Parse request path
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($request_uri, PHP_URL_PATH) ?? '/';

        // Remove base path if not root
        $base_path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($base_path !== '/' && $base_path !== '.') {
            $path = substr($path, strlen($base_path));
        }

        // Ensure path starts with /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Parse segments (filter empty values, reindex)
        $segments = array_values(array_filter(explode('/', $path)));

        // Parse headers from $_SERVER
        $headers = self::parseHeaders();

        // Parse JSON body for POST, PUT, PATCH requests
        $body = null;
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $rawBody = file_get_contents('php://input');
            if ($rawBody !== '' && $rawBody !== false) {
                $decoded = json_decode($rawBody, true);
                if ($decoded !== null) {
                    $body = $decoded;
                }
            }
        }

        // Parse query parameters
        $query = $_GET ?? [];

        return new self($method, $path, $segments, $headers, $body, $query);
    }

    /**
     * Extract HTTP headers from $_SERVER
     *
     * @return array<string, string>
     */
    private static function parseHeaders(): array {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            // Extract HTTP_* headers
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = substr($key, 5);
                $headerName = str_replace('_', '-', $headerName);
                $headerName = strtolower($headerName);
                $headers[$headerName] = $value;
            }
            // Add CONTENT_TYPE and CONTENT_LENGTH if present
            elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $headerName = str_replace('_', '-', strtolower($key));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    /**
     * Create new Request with injected route parameters
     *
     * Used after routing to inject matched route parameters.
     * Returns new instance (immutable).
     *
     * @param array<string, string> $params Route parameters
     */
    public function withParams(array $params): self {
        return new self(
            $this->method,
            $this->path,
            $this->segments,
            $this->headers,
            $this->body,
            $this->query,
            $params
        );
    }

    /**
     * Get a header value (case-insensitive)
     *
     * @return string|null
     */
    public function header(string $name): ?string {
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }

    /**
     * Get a query parameter
     *
     * @return string|null
     */
    public function queryParam(string $name): ?string {
        return $this->query[$name] ?? null;
    }

    /**
     * Get a body field
     *
     * @return mixed
     */
    public function bodyParam(string $name): mixed {
        return $this->body[$name] ?? null;
    }

    /**
     * Get a route parameter
     *
     * @return string|null
     */
    public function param(string $name): ?string {
        return $this->params[$name] ?? null;
    }
}
