<?php

namespace Equidna\DomainTokenAuth\Providers;

use Equidna\DomainTokenAuth\Console\Commands\GenerateDomainToken;
use Equidna\DomainTokenAuth\DomainToken;
use Equidna\DomainTokenAuth\Http\Middleware\ValidateDomainToken;
use Equidna\DomainTokenAuth\Services\DomainTokenManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class DomainTokenAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/domain-token-auth.php', 'domain-token-auth');

        $this->app->singleton(DomainTokenManager::class, fn() => new DomainTokenManager());

        $this->app->singleton(DomainToken::class, fn($app) => new DomainToken(
            manager: $app->make(DomainTokenManager::class),
            request: $app['request'],
        ));
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('domain-token', ValidateDomainToken::class);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../../config/domain-token-auth.php' => config_path('domain-token-auth.php'),
        ], 'domain-token-auth-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'domain-token-auth-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDomainToken::class,
            ]);
        }
    }
}
