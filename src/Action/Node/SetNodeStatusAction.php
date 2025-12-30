<?php declare(strict_types=1);

namespace Internet\Graph\Action\Node;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Set Node Status Action
 *
 * Sets the status of a node with validation
 */
class SetNodeStatusAction extends AbstractAction
{
    private const ALLOWED_STATUSES = ['unknown', 'healthy', 'unhealthy', 'maintenance'];

    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Set status of a node
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments (id)
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $nodeId = urldecode($args['id']);
        $body = $this->getParsedBody($request);

        // Validate required field
        if (!isset($body['status'])) {
            return $this->jsonResponse($response, [
                'error' => 'Missing required field: status'
            ], 400);
        }

        // Validate status value
        if (!in_array($body['status'], self::ALLOWED_STATUSES, true)) {
            return $this->jsonResponse($response, [
                'error' => 'Invalid status. Allowed values: ' . implode(', ', self::ALLOWED_STATUSES)
            ], 400);
        }

        if ($this->graph->set_node_status($nodeId, $body['status'])) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Node status set successfully',
                'data' => ['node_id' => $nodeId, 'status' => $body['status']]
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Failed to set node status (node may not exist)'
        ], 404);
    }
}
