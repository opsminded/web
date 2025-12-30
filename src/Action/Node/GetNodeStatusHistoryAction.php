<?php declare(strict_types=1);

namespace Internet\Graph\Action\Node;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Get Node Status History Action
 *
 * Gets the complete status history of a node
 */
class GetNodeStatusHistoryAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Get status history of a node
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments (id)
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $nodeId = urldecode($args['id']);
        $history = $this->graph->get_node_status_history($nodeId);

        $result = [];
        foreach ($history as $status) {
            $result[] = $status->to_array();
        }

        return $this->jsonResponse($response, [
            'history' => $result
        ]);
    }
}
