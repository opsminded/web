<?php declare(strict_types=1);

namespace Internet\Graph;

/**
 * Configuration loader
 * Loads configuration from .env file or environment variables
 */
class Config {
    private static ?array $config = null;

    /**
     * Load configuration from .env file
     */
    public static function load(?string $env_file = null): void {
        if (self::$config !== null) {
            return; // Already loaded
        }

        if ($env_file === null) {
            $env_file = __DIR__ . '/../.env';
        }

        self::$config = [];

        // Load from environment variables first
        self::loadFromEnvironment();

        // Override with .env file if it exists
        if (file_exists($env_file)) {
            self::loadFromFile($env_file);
        }

        // Set defaults for missing values
        self::setDefaults();
    }

    /**
     * Load configuration from environment variables
     */
    private static function loadFromEnvironment(): void {
        $env_vars = [
            'APP_ENV', 'DB_PATH', 'AUTH_USERS', 'AUTH_BEARER_TOKENS',
            'SESSION_LIFETIME', 'SESSION_NAME',
            'CORS_ALLOWED_ORIGINS', 'CORS_ALLOWED_METHODS', 'CORS_ALLOWED_HEADERS',
            'RATE_LIMIT_ENABLED', 'RATE_LIMIT_MAX_REQUESTS', 'RATE_LIMIT_WINDOW_SECONDS'
        ];

        foreach ($env_vars as $var) {
            $value = getenv($var);
            if ($value !== false) {
                self::$config[$var] = $value;
            }
        }
    }

    /**
     * Load configuration from .env file
     */
    private static function loadFromFile(string $file): void {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                self::$config[$key] = $value;
            }
        }
    }

    /**
     * Set default values
     */
    private static function setDefaults(): void {
        $defaults = [
            'APP_ENV' => 'production',
            'DB_PATH' => '../graph.db',
            'AUTH_USERS' => '',
            'AUTH_BEARER_TOKENS' => '',
            'SESSION_LIFETIME' => '3600',
            'SESSION_NAME' => 'gdmon_session',
            'CORS_ALLOWED_ORIGINS' => '*',
            'CORS_ALLOWED_METHODS' => 'GET,POST,PUT,DELETE,OPTIONS',
            'CORS_ALLOWED_HEADERS' => 'Content-Type,Authorization',
            'RATE_LIMIT_ENABLED' => 'false',
            'RATE_LIMIT_MAX_REQUESTS' => '100',
            'RATE_LIMIT_WINDOW_SECONDS' => '60'
        ];

        foreach ($defaults as $key => $value) {
            if (!isset(self::$config[$key])) {
                self::$config[$key] = $value;
            }
        }
    }

    /**
     * Get a configuration value
     */
    public static function get(string $key, mixed $default = null): mixed {
        if (self::$config === null) {
            self::load();
        }

        return self::$config[$key] ?? $default;
    }

    /**
     * Get all configuration
     */
    public static function all(): array {
        if (self::$config === null) {
            self::load();
        }

        return self::$config;
    }

    /**
     * Parse AUTH_USERS into array
     * Format: "user1:hash1\nuser2:hash2" or "user1:hash1"
     */
    public static function getAuthUsers(): array {
        $users_str = self::get('AUTH_USERS', '');
        if (empty($users_str)) {
            return [];
        }

        $users = [];
        $lines = explode("\n", $users_str);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (strpos($line, ':') !== false) {
                list($username, $hash) = explode(':', $line, 2);
                $users[trim($username)] = trim($hash);
            }
        }

        return $users;
    }

    /**
     * Parse AUTH_BEARER_TOKENS into array
     * Format: "token1:user1\ntoken2:user2" or "token1:user1"
     */
    public static function getAuthBearerTokens(): array {
        $tokens_str = self::get('AUTH_BEARER_TOKENS', '');
        if (empty($tokens_str)) {
            return [];
        }

        $tokens = [];
        $lines = explode("\n", $tokens_str);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (strpos($line, ':') !== false) {
                list($token, $user) = explode(':', $line, 2);
                $tokens[trim($token)] = trim($user);
            }
        }

        return $tokens;
    }

    /**
     * Check if running in development mode
     */
    public static function isDevelopment(): bool {
        return self::get('APP_ENV') === 'development';
    }

    /**
     * Check if running in production mode
     */
    public static function isProduction(): bool {
        return self::get('APP_ENV') === 'production';
    }
}
