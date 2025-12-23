<?php declare(strict_types=1);

namespace Internet\Graph;

/**
 * Authenticator - Handles authentication for API requests
 *
 * Supports:
 * - Bearer token authentication for automation
 * - Basic auth for user authentication
 * - Anonymous access for read-only operations
 */
class Authenticator {
    private array $validBearerTokens;
    private array $validUsers;

    /**
     * @param array $validBearerTokens Map of token => user_identifier
     * @param array $validUsers Map of username => password_hash
     */
    public function __construct(array $validBearerTokens, array $validUsers) {
        $this->validBearerTokens = $validBearerTokens;
        $this->validUsers = $validUsers;
    }

    /**
     * Authenticate the current request
     *
     * @return string|null User identifier if authenticated, null otherwise
     */
    public function authenticate(): ?string {
        $auth_header = $this->getAuthHeader();

        if ($auth_header === null) {
            return null;
        }

        // Check for Bearer token (for automations)
        if (preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
            return $this->authenticateBearerToken($matches[1]);
        }

        // Check for Basic Auth
        if (preg_match('/Basic\s+(.+)/i', $auth_header, $matches)) {
            return $this->authenticateBasicAuth($matches[1]);
        }

        return null;
    }

    /**
     * Get the Authorization header from the request
     */
    private function getAuthHeader(): ?string {
        // Check standard locations
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

        if ($auth_header === null) {
            // Check alternative header locations (Apache)
            if (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $auth_header = $headers['Authorization'] ?? null;
            }
        }

        return $auth_header;
    }

    /**
     * Authenticate using Bearer token
     */
    private function authenticateBearerToken(string $token): ?string {
        if (isset($this->validBearerTokens[$token])) {
            return $this->validBearerTokens[$token];
        }

        return null;
    }

    /**
     * Authenticate using Basic Auth credentials
     */
    private function authenticateBasicAuth(string $encodedCredentials): ?string {
        $credentials = base64_decode($encodedCredentials);
        list($username, $password) = explode(':', $credentials, 2);

        if (isset($this->validUsers[$username])) {
            if (password_verify($password, $this->validUsers[$username])) {
                return $username;
            }
        }

        return null;
    }
}
