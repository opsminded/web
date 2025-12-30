<?php declare(strict_types=1);

namespace Internet\Graph\Action\Audit;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Get Audit History Action
 *
 * Retrieves audit log entries with optional filtering
 */
class GetAuditHistoryAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Get audit history
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $params = $this->getQueryParams($request);

        $entityType = $params['entity_type'] ?? null;
        $entityId = $params['entity_id'] ?? null;

        $history = $this->graph->get_audit_history($entityType, $entityId);

        return $this->jsonResponse($response, [
            'audit_log' => $history
        ]);
    }
}
