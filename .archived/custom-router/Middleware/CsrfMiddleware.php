<?php declare(strict_types=1);

namespace Internet\Graph\Middleware;

use Internet\Graph\Http\Request;
use Internet\Graph\Http\Response;
use Internet\Graph\SessionManager;

/**
 * CSRF Protection Middleware
 *
 * Validates CSRF tokens for session-authenticated state-changing requests.
 * Exempts:
 * - GET requests (read-only)
 * - Bearer token authentication (API automation)
 * - Login endpoint
 */
class CsrfMiddleware {
    /**
     * Validate CSRF token if required
     *
     * @param callable $next Next middleware or handler
     * @param array<string, mixed> $context Shared context
     */
    public function __invoke(Request $request, callable $next, array $context): Response {
        // Check if CSRF validation is required
        $isSessionAuth = SessionManager::isAuthenticated();
        $isStateChanging = in_array($request->method, ['POST', 'PUT', 'DELETE', 'PATCH']);
        $isLoginRoute = $request->path === '/api.php/auth/login';

        // Skip CSRF for non-session auth, read-only, or login
        if (!$isSessionAuth || !$isStateChanging || $isLoginRoute) {
            return $next($request);
        }

        // Get CSRF token from header or body
        $token = $request->header('x-csrf-token') ?? $request->bodyParam('csrf_token');

        // Validate token
        if (!SessionManager::validateCsrfToken($token ?? '')) {
            return Response::forbidden(
                'CSRF token validation failed. ' .
                'Please include a valid CSRF token in X-CSRF-Token header or request body'
            );
        }

        // CSRF validation passed, continue to next middleware/handler
        return $next($request);
    }
}
