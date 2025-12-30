<?php declare(strict_types=1);

namespace Internet\Graph\Action\Edge;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Create Edge Action
 *
 * Creates a new edge between two nodes
 */
class CreateEdgeAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Create a new edge
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
        if (!isset($body['id']) || !isset($body['source']) || !isset($body['target'])) {
            return $this->jsonResponse($response, [
                'error' => 'Missing required fields: id, source, target'
            ], 400);
        }

        // Create edge
        if ($this->graph->add_edge($body['id'], $body['source'], $body['target'], $body['data'] ?? [])) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Edge created successfully',
                'data' => ['id' => $body['id']]
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Edge creation failed'
        ], 400);
    }
}
