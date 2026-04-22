<?php

namespace Equidna\DomainTokenAuth\Tests;

use Equidna\DomainTokenAuth\Facades\DomainToken;
use Equidna\DomainTokenAuth\Providers\DomainTokenAuthServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    private const BEE_HIVE_PROVIDER = 'Equidna\\BeeHive\\BeeHiveServiceProvider';
    private const BEE_HIVE_RESOLVER = 'Equidna\\BeeHive\\Tenancy\\Resolvers\\StaticTenantResolver';
    private const BEE_HIVE_TENANT_CONTEXT = 'Equidna\\BeeHive\\Tenancy\\TenantContext';

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        if ($this->hasBeeHive()) {
            $app['config']->set('bee-hive.resolver', self::BEE_HIVE_RESOLVER);
            $app['config']->set('bee-hive.strict', false);
        }

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
        $providers = [
            DomainTokenAuthServiceProvider::class,
        ];

        if ($this->hasBeeHive()) {
            $providers[] = self::BEE_HIVE_PROVIDER;
        }

        return $providers;
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

        Route::middleware('domain-token:user')->get('/secured-data', function () {
            return response()->json([
                'data' => DomainToken::data(),
                'client' => DomainToken::data('client'),
                'missing' => DomainToken::data('missing', 'fallback'),
            ]);
        });

        Route::middleware('domain-token:tenant_user')->get('/tenant-secured', function () {
            $tenantId = null;

            if ($this->hasBeeHive() && app()->bound(self::BEE_HIVE_TENANT_CONTEXT)) {
                $tenantId = app(self::BEE_HIVE_TENANT_CONTEXT)->get();
            }

            return response()->json([
                'tenant_id' => $tenantId,
            ]);
        });
    }

    protected function hasBeeHive(): bool
    {
        return class_exists(self::BEE_HIVE_PROVIDER);
    }
}
