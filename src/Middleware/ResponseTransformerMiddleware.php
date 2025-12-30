<?php declare(strict_types=1);

namespace Internet\Graph\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * Response Transformer Middleware
 *
 * Wraps all successful responses in a consistent format with metadata
 *
 * Standard format:
 * {
 *   "success": true,
 *   "data": {...},
 *   "meta": {
 *     "timestamp": "2025-12-30T12:00:00Z",
 *     "version": "1.0"
 *   }
 * }
 */
class ResponseTransformerMiddleware implements MiddlewareInterface
{
    private const API_VERSION = '1.0';

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $response = $handler->handle($request);

        // Only transform JSON responses
        $contentType = $response->getHeaderLine('Content-Type');
        if (strpos($contentType, 'application/json') === false) {
            return $response;
        }

        // Don't transform responses that already have the standard format
        $body = (string)$response->getBody();
        $data = json_decode($body, true);

        // If already has meta field, assume it's already transformed
        if (isset($data['meta'])) {
            return $response;
        }

        // Check if this is an error response (status >= 400)
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            // Error responses are handled by ExceptionHandlerMiddleware
            // Just ensure they have meta and success: false
            if (!isset($data['meta'])) {
                $data['success'] = false;
                $data['meta'] = $this->getMeta();

                $newResponse = new SlimResponse();
                $newResponse->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                return $newResponse
                    ->withStatus($statusCode)
                    ->withHeader('Content-Type', 'application/json');
            }
            return $response;
        }

        // Transform successful response
        // If the response already has a "success" field, preserve the structure
        // but add metadata
        if (isset($data['success'])) {
            $data['meta'] = $this->getMeta();
            $transformedData = $data;
        } else {
            // Wrap plain data in standard format
            $transformedData = [
                'success' => true,
                'data' => $data,
                'meta' => $this->getMeta()
            ];
        }

        $newResponse = new SlimResponse();
        $newResponse->getBody()->write(json_encode($transformedData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $newResponse
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get metadata for response
     */
    private function getMeta(): array
    {
        return [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'version' => self::API_VERSION
        ];
    }
}
