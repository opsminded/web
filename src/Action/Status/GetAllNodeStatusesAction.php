<?php declare(strict_types=1);

namespace Internet\Graph\Action\Status;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Get All Node Statuses Action
 *
 * Returns status for all nodes
 */
class GetAllNodeStatusesAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Get status of all nodes
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $statuses = $this->graph->status();

        $result = [];
        foreach ($statuses as $status) {
            $result[] = $status->to_array();
        }

        return $this->jsonResponse($response, [
            'statuses' => $result
        ]);
    }
}
