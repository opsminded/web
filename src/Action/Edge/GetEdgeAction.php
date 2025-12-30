<?php declare(strict_types=1);

namespace Internet\Graph\Action\Edge;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Get Edge Action
 *
 * Checks if an edge exists
 */
class GetEdgeAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Check if an edge exists
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments (id)
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = urldecode($args['id']);
        $exists = $this->graph->edge_exists_by_id($id);

        return $this->jsonResponse($response, [
            'exists' => $exists,
            'id' => $id
        ]);
    }
}
