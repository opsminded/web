<?php declare(strict_types=1);

namespace Internet\Graph\Action\Edge;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Delete Edge Action
 *
 * Removes an edge from the graph
 */
class DeleteEdgeAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Remove an edge
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments (id)
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = urldecode($args['id']);

        if ($this->graph->remove_edge($id)) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Edge removed successfully',
                'data' => ['id' => $id]
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Edge not found or removal failed'
        ], 404);
    }
}
