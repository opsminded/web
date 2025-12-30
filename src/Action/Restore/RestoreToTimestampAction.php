<?php declare(strict_types=1);

namespace Internet\Graph\Action\Restore;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Restore To Timestamp Action
 *
 * Restores the entire graph to a specific point in time
 */
class RestoreToTimestampAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Restore graph to a specific timestamp
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $body = $this->getParsedBody($request);

        // Validate required field
        if (!isset($body['timestamp'])) {
            return $this->jsonResponse($response, [
                'error' => 'Missing required field: timestamp'
            ], 400);
        }

        if ($this->graph->restore_to_timestamp($body['timestamp'])) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Graph restored to timestamp successfully'
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Restore failed'
        ], 400);
    }
}
