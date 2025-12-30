<?php declare(strict_types=1);

namespace Internet\Graph\Action\Restore;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Restore Entity Action
 *
 * Restores a specific entity to a previous state
 */
class RestoreEntityAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Restore a specific entity
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $body = $this->getParsedBody($request);

        // Validate required fields
        if (!isset($body['entity_type']) || !isset($body['entity_id']) || !isset($body['audit_log_id'])) {
            return $this->jsonResponse($response, [
                'error' => 'Missing required fields'
            ], 400);
        }

        if ($this->graph->restore_entity($body['entity_type'], $body['entity_id'], (int)$body['audit_log_id'])) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Entity restored successfully'
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Restore failed'
        ], 400);
    }
}
