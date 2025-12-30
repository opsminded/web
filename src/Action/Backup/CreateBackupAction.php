<?php declare(strict_types=1);

namespace Internet\Graph\Action\Backup;

use Internet\Graph\Action\AbstractAction;
use Internet\Graph\Graph;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Create Backup Action
 *
 * Creates a database backup
 */
class CreateBackupAction extends AbstractAction
{
    public function __construct(
        private Graph $graph
    ) {
    }

    /**
     * Create a backup
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param array $args Route arguments
     * @return Response JSON response
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $body = $this->getParsedBody($request);
        $backupName = $body['name'] ?? null;

        $result = $this->graph->create_backup($backupName);

        if ($result['success']) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Backup created successfully',
                'data' => $result
            ]);
        }

        return $this->jsonResponse($response, [
            'success' => false,
            'error' => 'Backup failed',
            'details' => $result
        ], 500);
    }
}
