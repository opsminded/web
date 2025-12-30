<?php declare(strict_types=1);

namespace Internet\Graph\Action\Auth;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\SessionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Logout Action
 *
 * Handles user logout and session destruction
 */
class LogoutAction extends AbstractAction
{
    /**
     * Handle logout request
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        SessionManager::destroy();
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }
}
