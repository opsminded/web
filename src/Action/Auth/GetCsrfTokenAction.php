<?php declare(strict_types=1);

namespace Internet\Graph\Action\Auth;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\SessionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Get CSRF Token Action
 *
 * Returns CSRF token for authenticated users
 */
class GetCsrfTokenAction extends AbstractAction
{
    /**
     * Get CSRF token
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        if (!SessionManager::isAuthenticated()) {
            return $this->jsonResponse($response, [
                'error' => 'Not authenticated'
            ], 401);
        }

        return $this->jsonResponse($response, [
            'csrf_token' => SessionManager::getCsrfToken()
        ]);
    }
}
