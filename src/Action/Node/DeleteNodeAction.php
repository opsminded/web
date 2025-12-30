<?php declare(strict_types=1);

namespace Internet\Graph\Action\Node;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Delete Node Action
 *
 * Removes a node from the graph
 */
class DeleteNodeAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Remove a node
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments (id)
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = urldecode($args['id']);

        if ($this->graph->remove_node($id)) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Node removed successfully',
                'data' => ['id' => $id]
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Node not found or removal failed'
        ], 404);
    }
}
