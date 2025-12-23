<?php declare (strict_types=1);

namespace Internet\Graph;


/**
 * Global context for audit logging
 * Stores user_id and ip_address that can be accessed from anywhere
 */
class AuditContext {
    private static ?string $user_id = null;
    private static ?string $ip_address = null;

    /**
     * Set the audit context for the current request
     */
    public static function set(?string $user_id, ?string $ip_address): void {
        self::$user_id = $user_id;
        self::$ip_address = $ip_address;
    }

    /**
     * Set only the user ID
     */
    public static function set_user(?string $user_id): void {
        self::$user_id = $user_id;
    }

    /**
     * Set only the IP address
     */
    public static function set_ip(?string $ip_address): void {
        self::$ip_address = $ip_address;
    }

    /**
     * Get the current user ID
     */
    public static function get_user(): ?string {
        return self::$user_id;
    }

    /**
     * Get the current IP address
     */
    public static function get_ip(): ?string {
        return self::$ip_address;
    }

    /**
     * Get both user ID and IP address as an array
     */
    public static function get(): array {
        return [
            'user_id' => self::$user_id,
            'ip_address' => self::$ip_address
        ];
    }

    /**
     * Clear the audit context
     */
    public static function clear(): void {
        self::$user_id = null;
        self::$ip_address = null;
    }

    /**
     * Initialize context from common PHP globals
     * This is a convenience method for typical web requests
     */
    public static function init_from_request(?string $user_id = null): void {
        // Get IP address from various possible headers/server vars
        $ip = null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Handle proxy/load balancer
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        self::set($user_id, $ip);
    }
}