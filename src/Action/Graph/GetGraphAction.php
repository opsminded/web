<?php declare(strict_types=1);

namespace Internet\Graph\Action\Graph;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Get Graph Action
 *
 * Returns the entire graph structure with all nodes and edges
 */
class GetGraphAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Get the entire graph
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response with graph data
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $data = $this->graph->get();
        return $this->jsonResponse($response, $data);
    }
}
