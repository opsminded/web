<?php declare(strict_types=1);

namespace Internet\Graph\Action;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Abstract Action Base Class
 *
 * All action classes should extend this base class and implement __invoke()
 */
abstract class AbstractAction
{
    /**
     * Execute the action
     *
     * @param Request $request The HTTP request
     * @param Response $response The HTTP response
     * @param array $args Route parameters
     * @return Response The HTTP response
     */
    abstract public function __invoke(
        Request $request,
        Response $response,
        array $args
    ): Response;

    /**
     * Helper method to create JSON response
     *
     * @param Response $response The response object
     * @param mixed $data Data to encode as JSON
     * @param int $status HTTP status code
     * @return Response JSON response
     */
    protected function jsonResponse(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get request body as parsed array
     *
     * @param Request $request The request object
     * @return array The parsed body
     */
    protected function getParsedBody(Request $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }

    /**
     * Get query parameters
     *
     * @param Request $request The request object
     * @return array The query parameters
     */
    protected function getQueryParams(Request $request): array
    {
        return $request->getQueryParams();
    }
}
