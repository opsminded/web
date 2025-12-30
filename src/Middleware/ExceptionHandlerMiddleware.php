<?php declare(strict_types=1);

namespace Internet\Graph\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Internet\Graph\Exception\GraphException;
use Slim\Psr7\Response as SlimResponse;

/**
 * Exception Handler Middleware
 *
 * Catches GraphException instances and converts them to properly formatted JSON responses
 * with consistent structure including metadata
 */
class ExceptionHandlerMiddleware implements MiddlewareInterface
{
    private const API_VERSION = '1.0';

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (GraphException $e) {
            return $this->handleGraphException($e);
        } catch (\Throwable $e) {
            return $this->handleGenericException($e);
        }
    }

    /**
     * Handle GraphException and convert to JSON response
     */
    private function handleGraphException(GraphException $e): Response
    {
        $response = new SlimResponse();

        $data = [
            'success' => false,
            'error' => $e->getMessage(),
        ];

        // Include context if available
        $context = $e->getContext();
        if (!empty($context)) {
            $data['context'] = $context;
        }

        // Add metadata
        $data['meta'] = $this->getMeta();

        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $response
            ->withStatus($e->getHttpStatusCode())
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Handle generic exceptions (unexpected errors)
     */
    private function handleGenericException(\Throwable $e): Response
    {
        $response = new SlimResponse();

        $data = [
            'success' => false,
            'error' => 'Internal server error',
            'message' => $e->getMessage(),
        ];

        // In development, include stack trace
        if (getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === 'true') {
            $data['trace'] = $e->getTraceAsString();
        }

        // Add metadata
        $data['meta'] = $this->getMeta();

        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $response
            ->withStatus(500)
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
