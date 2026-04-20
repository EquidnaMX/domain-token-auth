<?php

namespace Equidna\DomainTokenAuth\Tests;

use Equidna\BeeHive\BeeHiveServiceProvider;
use Equidna\BeeHive\Tenancy\Resolvers\StaticTenantResolver;
use Equidna\BeeHive\Tenancy\TenantContext;
use Equidna\DomainTokenAuth\Providers\DomainTokenAuthServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('bee-hive.tenant_key', 'tenant_id');
        $app['config']->set('bee-hive.resolver', StaticTenantResolver::class);
        $app['config']->set('bee-hive.static_tenant_id', null);
        $app['config']->set('bee-hive.strict', false);

        $app['config']->set('domain-token-auth.domains', [
            'user' => [
                'model' => FakeUser::class,
                'default_actions' => ['users.read'],
                'roles' => [
                    'viewer' => ['users.read'],
                    'admin' => ['users.*'],
                ],
                'default_ttl_minutes' => 60,
            ],
            'app' => [
                'model' => FakeApplication::class,
                'default_actions' => ['apps.read'],
                'roles' => [
                    'integrator' => ['apps.read', 'apps.write'],
                    'owner' => ['apps.*'],
                ],
                'default_ttl_minutes' => 60,
            ],
            'tenant_user' => [
                'model' => FakeTenantUser::class,
                'default_actions' => ['tenant-users.read'],
                'roles' => [],
                'default_ttl_minutes' => 60,
            ],
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            BeeHiveServiceProvider::class,
            DomainTokenAuthServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../src/database/migrations');

        $this->app['db']->connection()->getSchemaBuilder()->create('fake_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('fake_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('fake_tenant_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();
        });

        Route::middleware('domain-token:user')->get('/secured', function () {
            return response()->json(['ok' => true]);
        });

        Route::middleware('domain-token:tenant_user')->get('/tenant-secured', function () {
            return response()->json([
                'tenant_id' => app(TenantContext::class)->get(),
            ]);
        });
    }
}
