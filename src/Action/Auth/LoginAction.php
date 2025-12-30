<?php declare(strict_types=1);

namespace Internet\Graph\Action\Auth;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Config;
use Internet\Graph\SessionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Login Action
 *
 * Handles user login with session creation
 */
class LoginAction extends AbstractAction
{
    /**
     * Handle login request
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $body = $this->getParsedBody($request);
        $username = $body['username'] ?? null;
        $password = $body['password'] ?? null;

        if (!$username || !$password) {
            return $this->jsonResponse($response, [
                'error' => 'Missing username or password'
            ], 400);
        }

        $validUsers = Config::getAuthUsers();

        if (isset($validUsers[$username]) && password_verify($password, $validUsers[$username])) {
            SessionManager::setUser($username);
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $username,
                    'csrf_token' => SessionManager::getCsrfToken()
                ]
            ]);
        }

        return $this->jsonResponse($response, [
            'error' => 'Invalid credentials'
        ], 401);
    }
}
