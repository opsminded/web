<?php declare(strict_types=1);

namespace Internet\Graph;

/**
 * Session Manager for secure session handling
 */
class SessionManager {
    private static bool $started = false;

    /**
     * Start a secure session
     */
    public static function start(): void {
        if (self::$started) {
            return;
        }

        // Prevent session fixation
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session configuration
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_samesite', 'Strict');

            // Use secure cookies in production
            if (Config::isProduction()) {
                ini_set('session.cookie_secure', '1');
            }

            // Set session name from config
            session_name(Config::get('SESSION_NAME', 'gdmon_session'));

            // Set session lifetime
            $lifetime = (int)Config::get('SESSION_LIFETIME', 3600);
            ini_set('session.gc_maxlifetime', (string)$lifetime);

            session_start();

            // Regenerate session ID to prevent fixation
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
                $_SESSION['created_at'] = time();
            }

            // Check session timeout
            if (isset($_SESSION['last_activity'])) {
                $elapsed = time() - $_SESSION['last_activity'];
                if ($elapsed > $lifetime) {
                    self::destroy();
                    return;
                }
            }

            $_SESSION['last_activity'] = time();
            self::$started = true;
        }
    }

    /**
     * Set user session
     */
    public static function setUser(string $user_id): void {
        self::start();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['authenticated'] = true;
        $_SESSION['auth_time'] = time();

        // Regenerate ID on authentication
        session_regenerate_id(true);
    }

    /**
     * Get current user
     */
    public static function getUser(): ?string {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool {
        self::start();
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    /**
     * Destroy session
     */
    public static function destroy(): void {
        self::start();
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken(): string {
        self::start();

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    public static function validateCsrfToken(string $token): bool {
        self::start();

        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get CSRF token (same as generate, for consistency)
     */
    public static function getCsrfToken(): string {
        return self::generateCsrfToken();
    }

    /**
     * Set a flash message
     */
    public static function setFlash(string $key, mixed $value): void {
        self::start();
        $_SESSION['flash'][$key] = $value;
    }

    /**
     * Get and clear a flash message
     */
    public static function getFlash(string $key): mixed {
        self::start();

        if (isset($_SESSION['flash'][$key])) {
            $value = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $value;
        }

        return null;
    }
}
