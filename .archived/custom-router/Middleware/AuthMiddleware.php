<?php declare(strict_types=1);

namespace Internet\Graph\Middleware;

use Internet\Graph\Http\Request;
use Internet\Graph\Http\Response;

/**
 * Authentication Middleware
 *
 * Requires authentication for state-changing operations (POST, PUT, DELETE, PATCH).
 * Allows anonymous access for read-only operations (GET).
 * Exempts /auth/login endpoint.
 */
class AuthMiddleware {
    /**
     * Check authentication requirements
     *
     * @param callable $next Next middleware or handler
     * @param array<string, mixed> $context Shared context with 'user_id'
     */
    public function __invoke(Request $request, callable $next, array $context): Response {
        // Check if route requires authentication
        $isReadOnly = $request->method === 'GET';
        $isLoginRoute = $request->path === '/api.php/auth/login';

        // State-changing operations require authentication (except login)
        if (!$isReadOnly && !$isLoginRoute) {
            $userId = $context['user_id'] ?? null;

            if ($userId === null || $userId === 'anonymous') {
                return Response::unauthorized(
                    'Authentication is required for ' . $request->method . ' requests. ' .
                    'Please login or provide valid Basic Auth credentials or Bearer token'
                );
            }
        }

        // Authentication passed, continue to next middleware/handler
        return $next($request);
    }
}
