<?php declare(strict_types=1);

namespace Internet\Graph\Action\Node;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Get Node Status Action
 *
 * Gets the current status of a node
 */
class GetNodeStatusAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Get status of a specific node
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments (id)
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $nodeId = urldecode($args['id']);
        $status = $this->graph->get_node_status($nodeId);

        if ($status !== null) {
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $status->to_array()
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'No status found for node'
        ], 404);
    }
}
