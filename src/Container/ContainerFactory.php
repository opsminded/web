<?php declare(strict_types=1);

namespace Internet\Graph\Container;

use DI\ContainerBuilder;
use Internet\Graph\Graph;
use Internet\Graph\Authenticator;
use Internet\Graph\Config;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;

/**
 * Container Factory
 *
 * Creates and configures the PHP-DI dependency injection container
 */
class ContainerFactory
{
    /**
     * Create and configure the container
     *
     * @param string $dbFile Database file path
     * @return ContainerInterface
     */
    public static function create(string $dbFile): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();

        // Enable compilation for production (optional)
        // $containerBuilder->enableCompilation(__DIR__ . '/../../var/cache');

        $containerBuilder->addDefinitions([
            // Database file path
            'db_file' => $dbFile,

            // Graph service
            Graph::class => \DI\create(Graph::class)
                ->constructor(\DI\get('db_file')),

            // Authenticator service
            Authenticator::class => \DI\factory(function () {
                $validBearerTokens = Config::getAuthBearerTokens();
                $validUsers = Config::getAuthUsers();
                return new Authenticator($validBearerTokens, $validUsers);
            }),

            // Twig view service
            Twig::class => \DI\factory(function () {
                $templatePath = __DIR__ . '/../../templates';
                return Twig::create($templatePath, [
                    'cache' => false,
                    'debug' => true,
                ]);
            }),
        ]);

        return $containerBuilder->build();
    }
}
