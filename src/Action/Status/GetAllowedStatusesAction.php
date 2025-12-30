<?php declare(strict_types=1);

namespace Internet\Graph\Action\Status;

use Internet\Graph\Action\AbstractAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Get Allowed Statuses Action
 *
 * Returns list of allowed status values
 */
class GetAllowedStatusesAction extends AbstractAction
{
    private const ALLOWED_STATUSES = ['unknown', 'healthy', 'unhealthy', 'maintenance'];

    /**
     * Get allowed status values
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        return $this->jsonResponse($response, [
            'allowed_statuses' => self::ALLOWED_STATUSES
        ]);
    }
}
