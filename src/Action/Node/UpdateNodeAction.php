<?php declare(strict_types=1);

namespace Internet\Graph\Action\Node;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Update Node Action
 *
 * Updates an existing node with validation
 */
class UpdateNodeAction extends AbstractAction
{
    private const ALLOWED_CATEGORIES = ['business', 'application', 'infrastructure'];
    private const ALLOWED_TYPES = ['server', 'database', 'application', 'network'];

    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Update an existing node
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments (id)
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id = urldecode($args['id']);
        $body = $this->getParsedBody($request);

        // Validate required field
        if (!isset($body['data'])) {
            return $this->jsonResponse($response, [
                'error' => 'Missing required field: data'
            ], 400);
        }

        // Validate category if provided
        if (isset($body['data']['category'])) {
            if (empty($body['data']['category'])) {
                return $this->jsonResponse($response, [
                    'error' => 'Category cannot be empty. Allowed values: ' . implode(', ', self::ALLOWED_CATEGORIES)
                ], 400);
            }
            if (!in_array($body['data']['category'], self::ALLOWED_CATEGORIES, true)) {
                return $this->jsonResponse($response, [
                    'error' => 'Invalid category. Allowed values: ' . implode(', ', self::ALLOWED_CATEGORIES)
                ], 400);
            }
        }

        // Validate type if provided
        if (isset($body['data']['type'])) {
            if (empty($body['data']['type'])) {
                return $this->jsonResponse($response, [
                    'error' => 'Type cannot be empty. Allowed values: ' . implode(', ', self::ALLOWED_TYPES)
                ], 400);
            }
            if (!in_array($body['data']['type'], self::ALLOWED_TYPES, true)) {
                return $this->jsonResponse($response, [
                    'error' => 'Invalid type. Allowed values: ' . implode(', ', self::ALLOWED_TYPES)
                ], 400);
            }
        }

        // Update node
        if ($this->graph->update_node($id, $body['data'])) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Node updated successfully',
                'data' => ['id' => $id]
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Node not found or update failed'
        ], 404);
    }
}
