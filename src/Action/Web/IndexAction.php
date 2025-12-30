<?php declare(strict_types=1);

namespace Internet\Graph\Action\Web;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Index Action - Renders the main web interface
 */
class IndexAction
{
    public function __construct(
        private Twig $view
    ) {
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        return $this->view->render($response, 'index.html.twig', [
            // You can pass additional variables to the template here
            // For example: 'user' => $currentUser, 'config' => $config, etc.
        ]);
    }
}
