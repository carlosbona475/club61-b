<?php

declare(strict_types=1);

namespace Club61\Core;

use Club61\Http\Controllers\FeedController;
use Club61\Http\Middleware\AuthenticateMiddleware;
use Club61\Http\Middleware\SessionMiddleware;
use Club61\Http\Middleware\TouchLastSeenMiddleware;
use Club61\Infrastructure\Http\SupabaseRestClient;
use Club61\Repositories\PostRepository;
use Club61\Repositories\ProfileRepository;
use Club61\Repositories\StoryRepository;
use Club61\Services\FeedService;

final class Application
{
    private static ?Container $container = null;

    public static function init(Container $container): void
    {
        self::$container = $container;
    }

    public static function container(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException('Application not bootstrapped.');
        }

        return self::$container;
    }

    public static function registerBindings(Container $c): void
    {
        $c->singleton(Router::class, static function (): Router {
            /** @var array{middleware_groups: array<string, list<class-string>>, legacy_files: array<string, array{middleware: string, action: array{class-string, string}}>} $config */
            $config = require \CLUB61_BASE_PATH . '/routes/web.php';

            return new Router($config);
        });

        $c->singleton(MiddlewarePipeline::class, static fn (Container $container): MiddlewarePipeline => new MiddlewarePipeline($container));

        $c->singleton(SupabaseRestClient::class, static fn (): SupabaseRestClient => new SupabaseRestClient());
        $c->singleton(PostRepository::class, static fn (Container $container): PostRepository => new PostRepository($container->get(SupabaseRestClient::class)));
        $c->singleton(ProfileRepository::class, static fn (Container $container): ProfileRepository => new ProfileRepository($container->get(SupabaseRestClient::class)));
        $c->singleton(StoryRepository::class, static fn (Container $container): StoryRepository => new StoryRepository($container->get(SupabaseRestClient::class)));

        $c->singleton(FeedService::class, static fn (Container $container): FeedService => new FeedService(
            $container->get(PostRepository::class),
            $container->get(ProfileRepository::class),
            $container->get(StoryRepository::class),
            $container->get(SupabaseRestClient::class),
        ));

        $c->singleton(FeedController::class, static fn (Container $container): FeedController => new FeedController(
            $container->get(FeedService::class),
        ));

        $c->singleton(SessionMiddleware::class, static fn (): SessionMiddleware => new SessionMiddleware());
        $c->singleton(AuthenticateMiddleware::class, static fn (): AuthenticateMiddleware => new AuthenticateMiddleware());
        $c->singleton(TouchLastSeenMiddleware::class, static fn (): TouchLastSeenMiddleware => new TouchLastSeenMiddleware());
    }

    /**
     * Entrada a partir de um script legado em features/ (URL mantida).
     */
    public static function runLegacy(string $scriptPath): void
    {
        $container = self::container();
        $base = \CLUB61_BASE_PATH;
        $realBase = realpath($base);
        $realScript = realpath($scriptPath);
        if ($realBase === false || $realScript === false) {
            http_response_code(500);
            echo 'Caminho inválido.';

            return;
        }
        $realBase = str_replace('\\', '/', $realBase);
        $realScript = str_replace('\\', '/', $realScript);
        $prefix = rtrim($realBase, '/') . '/';
        if (!str_starts_with($realScript, $prefix)) {
            http_response_code(500);
            echo 'Script fora do projeto.';

            return;
        }
        $rel = ltrim(substr($realScript, strlen($prefix)), '/');

        $router = $container->get(Router::class);
        $match = $router->matchLegacyScript($rel);
        if ($match === null) {
            http_response_code(500);
            echo 'Rota legada não registada: ' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8');

            return;
        }

        $middlewareNames = $router->middlewareGroup($match['middleware']);
        $action = $match['action'];
        $request = Request::fromGlobals();

        $pipeline = $container->get(MiddlewarePipeline::class);
        $destination = static function (Request $req) use ($container, $action): void {
            [$controllerClass, $method] = $action;
            $ctrl = $container->get($controllerClass);
            $ctrl->{$method}($req);
        };

        $pipeline->dispatch($request, $middlewareNames, $destination);
    }

    /** Raiz do site (DirectoryIndex) — mantém redirecionamento para o feed. */
    public static function runWebFront(): void
    {
        header('Location: /features/feed/index.php');
        exit;
    }
}
