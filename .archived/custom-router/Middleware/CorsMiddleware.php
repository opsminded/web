<?php declare(strict_types=1);

namespace Internet\Graph\Middleware;

use Internet\Graph\Http\Request;
use Internet\Graph\Http\Response;
use Internet\Graph\Config;

/**
 * CORS Middleware
 *
 * Applies CORS headers to all responses.
 * Configuration comes from environment variables.
 */
class CorsMiddleware {
    /**
     * Apply CORS headers to response
     *
     * @param callable $next Next middleware or handler
     * @param array<string, mixed> $context Shared context
     */
    public function __invoke(Request $request, callable $next, array $context): Response {
        // Execute next middleware/handler
        $response = $next($request);

        // Add CORS headers to response
        return $response->withHeaders([
            'Access-Control-Allow-Origin' => Config::get('CORS_ALLOWED_ORIGINS', '*'),
            'Access-Control-Allow-Methods' => Config::get('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS'),
            'Access-Control-Allow-Headers' => Config::get('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization'),
        ]);
    }
}
