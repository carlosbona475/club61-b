<?php

declare(strict_types=1);

namespace Club61\Core;

use Club61\Controllers\AuthController;
use Club61\Controllers\FeedController;
use Club61\Controllers\LegacyController;
use Club61\Http\Middleware\AuthenticateMiddleware;
use Club61\Http\Middleware\SessionMiddleware;
use Club61\Http\Middleware\TouchLastSeenMiddleware;
use Club61\Infrastructure\Http\SupabaseRestClient;
use Club61\Repositories\PostRepository;
use Club61\Repositories\ProfileRepository;
use Club61\Repositories\StoryRepository;
use Club61\Services\LastSeenService;
use Club61\Services\SessionService;
use Club61\Services\ConfigBootstrapService;
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
            /** @var array{middleware_groups: array<string, list<class-string>>, routes: array<string, array{middleware: string, action: array{class-string, string}}>} $config */
            $config = require \CLUB61_BASE_PATH . '/routes/web.php';

            return new Router($config);
        });

        $c->singleton(MiddlewarePipeline::class, static fn (Container $container): MiddlewarePipeline => new MiddlewarePipeline($container));

        $c->singleton(SupabaseRestClient::class, static fn (): SupabaseRestClient => new SupabaseRestClient());
        $c->singleton(PostRepository::class, static fn (Container $container): PostRepository => new PostRepository($container->get(SupabaseRestClient::class)));
        $c->singleton(ProfileRepository::class, static fn (Container $container): ProfileRepository => new ProfileRepository($container->get(SupabaseRestClient::class)));
        $c->singleton(StoryRepository::class, static fn (Container $container): StoryRepository => new StoryRepository($container->get(SupabaseRestClient::class)));
        $c->singleton(ConfigBootstrapService::class, static fn (): ConfigBootstrapService => new ConfigBootstrapService());

        $c->singleton(FeedService::class, static fn (Container $container): FeedService => new FeedService(
            $container->get(PostRepository::class),
            $container->get(ProfileRepository::class),
            $container->get(StoryRepository::class),
            $container->get(SupabaseRestClient::class),
            $container->get(ConfigBootstrapService::class),
        ));

        $c->singleton(FeedController::class, static fn (Container $container): FeedController => new FeedController(
            $container->get(FeedService::class),
        ));
        $c->singleton(AuthController::class, static fn (): AuthController => new AuthController());
        $c->singleton(LegacyController::class, static fn (): LegacyController => new LegacyController());

        $c->singleton(SessionService::class, static fn (): SessionService => new SessionService());
        $c->singleton(LastSeenService::class, static fn (): LastSeenService => new LastSeenService());
        $c->singleton(SessionMiddleware::class, static fn (Container $container): SessionMiddleware => new SessionMiddleware(
            $container->get(SessionService::class),
        ));
        $c->singleton(AuthenticateMiddleware::class, static fn (Container $container): AuthenticateMiddleware => new AuthenticateMiddleware(
            $container->get(SessionService::class),
        ));
        $c->singleton(TouchLastSeenMiddleware::class, static fn (Container $container): TouchLastSeenMiddleware => new TouchLastSeenMiddleware(
            $container->get(LastSeenService::class),
            $container->get(SessionService::class),
        ));
    }

    public static function runHttp(): void
    {
        $container = self::container();
        $router = $container->get(Router::class);
        $request = Request::fromGlobals();
        $path = (string) ($request->server['REQUEST_URI'] ?? '/');
        $method = $request->method();
        $match = $router->match($method, $path);
        if ($match === null) {
            http_response_code(404);
            echo 'Rota não encontrada.';

            return;
        }

        $middlewareNames = $router->middlewareGroup($match['middleware']);
        $action = $match['action'];

        $pipeline = $container->get(MiddlewarePipeline::class);
        $destination = static function (Request $req) use ($container, $action): void {
            [$controllerClass, $method] = $action;
            $ctrl = $container->get($controllerClass);
            $ctrl->{$method}($req);
        };

        $pipeline->dispatch($request, $middlewareNames, $destination);
    }
}
