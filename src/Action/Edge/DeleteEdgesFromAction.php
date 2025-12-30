<?php declare(strict_types=1);

namespace Internet\Graph\Action\Edge;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Delete Edges From Action
 *
 * Removes all edges from a source node
 */
class DeleteEdgesFromAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Remove all edges from a source node
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments (source)
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $source = urldecode($args['source']);

        if ($this->graph->remove_edges_from($source)) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Edges removed successfully',
                'data' => ['source' => $source]
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Failed to remove edges'
        ], 400);
    }
}
