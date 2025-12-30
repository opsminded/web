<?php declare(strict_types=1);

namespace Internet\Graph\Action\Node;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Create Node Action
 *
 * Creates a new node with validation
 */
class CreateNodeAction extends AbstractAction
{
    private const ALLOWED_CATEGORIES = ['business', 'application', 'infrastructure'];
    private const ALLOWED_TYPES = ['server', 'database', 'application', 'network'];

    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Create a new node
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
        if (!isset($body['id']) || !isset($body['data'])) {
            return $this->jsonResponse($response, [
                'error' => 'Missing required fields: id, data'
            ], 400);
        }

        // Validate required category field
        if (!isset($body['data']['category']) || empty($body['data']['category'])) {
            return $this->jsonResponse($response, [
                'error' => 'Category is required. Allowed values: ' . implode(', ', self::ALLOWED_CATEGORIES)
            ], 400);
        }

        // Validate category value
        if (!in_array($body['data']['category'], self::ALLOWED_CATEGORIES, true)) {
            return $this->jsonResponse($response, [
                'error' => 'Invalid category. Allowed values: ' . implode(', ', self::ALLOWED_CATEGORIES)
            ], 400);
        }

        // Validate required type field
        if (!isset($body['data']['type']) || empty($body['data']['type'])) {
            return $this->jsonResponse($response, [
                'error' => 'Type is required. Allowed values: ' . implode(', ', self::ALLOWED_TYPES)
            ], 400);
        }

        // Validate type value
        if (!in_array($body['data']['type'], self::ALLOWED_TYPES, true)) {
            return $this->jsonResponse($response, [
                'error' => 'Invalid type. Allowed values: ' . implode(', ', self::ALLOWED_TYPES)
            ], 400);
        }

        // Create node
        if ($this->graph->add_node($body['id'], $body['data'])) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Node created successfully',
                'data' => ['id' => $body['id']]
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Node already exists or creation failed'
        ], 409);
    }
}
