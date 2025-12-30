<?php declare(strict_types=1);

namespace Internet\Graph\Action\Auth;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\SessionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Get Auth Status Action
 *
 * Returns current authentication status
 */
class GetAuthStatusAction extends AbstractAction
{
    /**
     * Get authentication status
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        return $this->jsonResponse($response, [
            'authenticated' => SessionManager::isAuthenticated(),
            'user' => SessionManager::getUser(),
            'csrf_token' => SessionManager::isAuthenticated() ? SessionManager::getCsrfToken() : null
        ]);
    }
}
