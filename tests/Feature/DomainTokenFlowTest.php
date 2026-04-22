<?php

namespace Equidna\DomainTokenAuth\Tests\Feature;

use Equidna\DomainTokenAuth\Facades\DomainToken;
use Equidna\DomainTokenAuth\Tests\FakeApplication;
use Equidna\DomainTokenAuth\Tests\FakeTenantUser;
use Equidna\DomainTokenAuth\Tests\FakeUser;
use Equidna\DomainTokenAuth\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DomainTokenFlowTest extends TestCase
{
    private const BEE_HIVE_TENANT_CONTEXT = 'Equidna\\BeeHive\\Tenancy\\TenantContext';

    public function test_domain_tokens_require_an_owner(): void
    {
        $columns = collect(DB::select('PRAGMA table_info(domain_tokens)'))
            ->keyBy('name');

        self::assertSame(1, (int) $columns->get('tokenable_type')->notnull);
        self::assertSame(1, (int) $columns->get('tokenable_id')->notnull);
    }

    public function test_can_issue_and_authenticate_token_for_domain(): void
    {
        $user = FakeUser::query()->create(['name' => 'Ada']);

        $issued = DomainToken::issue('user', $user, ['users.write']);

        $response = $this->getJson('/secured', [
            'Authorization' => 'Bearer ' . $issued->plainTextToken,
        ]);

        $response->assertOk();
    }

    public function test_persists_additional_token_data_on_issue(): void
    {
        $user = FakeUser::query()->create(['name' => 'Ada']);

        $issued = DomainToken::issue(
            domain: 'user',
            owner: $user,
            actions: ['users.read'],
            data: [
                'client' => 'mobile',
                'region' => 'mx',
                'level' => 2,
            ],
        );

        self::assertSame('mobile', $issued->token->data['client'] ?? null);
        self::assertSame('mx', $issued->token->data['region'] ?? null);
        self::assertSame(2, $issued->token->data['level'] ?? null);
    }

    public function test_can_consume_token_data_after_authentication(): void
    {
        $user = FakeUser::query()->create(['name' => 'Ada']);

        $issued = DomainToken::issue(
            domain: 'user',
            owner: $user,
            actions: ['users.read'],
            data: [
                'client' => 'mobile',
                'flags' => ['sync', 'reports'],
            ],
        );

        $response = $this->getJson('/secured-data', [
            'Authorization' => 'Bearer ' . $issued->plainTextToken,
        ]);

        $response->assertOk();
        $response->assertJsonPath('client', 'mobile');
        $response->assertJsonPath('data.flags.0', 'sync');
        $response->assertJsonPath('data.flags.1', 'reports');
        $response->assertJsonPath('missing', 'fallback');
    }


    public function test_role_mapped_actions_allow_authorization_checks(): void
    {
        $user = FakeUser::query()->create(['name' => 'Ada']);

        $issued = DomainToken::issue(
            domain: 'user',
            owner: $user,
            roles: ['admin'],
        );

        DomainToken::authenticate($issued->plainTextToken, 'user');

        self::assertTrue(DomainToken::can('users.delete', $issued->token));
    }

    public function test_rejects_token_with_wrong_domain(): void
    {
        $app = FakeApplication::query()->create(['name' => 'Integrator']);
        $issued = DomainToken::issue('app', $app, ['apps.read']);

        $response = $this->getJson('/secured', [
            'Authorization' => 'Bearer ' . $issued->plainTextToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_revoked_token(): void
    {
        $user = FakeUser::query()->create(['name' => 'Ada']);
        $issued = DomainToken::issue('user', $user, ['users.read']);
        DomainToken::revoke($issued->plainTextToken, 'security');

        $response = $this->getJson('/secured', [
            'Authorization' => 'Bearer ' . $issued->plainTextToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_token_before_start_window(): void
    {
        $user = FakeUser::query()->create(['name' => 'Ada']);

        $issued = DomainToken::issue(
            domain: 'user',
            owner: $user,
            actions: ['users.read'],
            startsAt: Carbon::now()->addHour(),
        );

        $response = $this->getJson('/secured', [
            'Authorization' => 'Bearer ' . $issued->plainTextToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_token_after_expiration(): void
    {
        $user = FakeUser::query()->create(['name' => 'Ada']);

        $issued = DomainToken::issue(
            domain: 'user',
            owner: $user,
            actions: ['users.read'],
            startsAt: Carbon::now()->subHours(2),
            expiresAt: Carbon::now()->subHour(),
        );

        $response = $this->getJson('/secured', [
            'Authorization' => 'Bearer ' . $issued->plainTextToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_orphan_token_when_owner_is_deleted(): void
    {
        $user = FakeUser::query()->create(['name' => 'Ada']);
        $issued = DomainToken::issue('user', $user, ['users.read']);

        $user->delete();

        $response = $this->getJson('/secured', [
            'Authorization' => 'Bearer ' . $issued->plainTextToken,
        ]);

        $response->assertStatus(401);
    }


    public function test_rejects_token_when_context_tenant_does_not_match(): void
    {
        if (! class_exists(self::BEE_HIVE_TENANT_CONTEXT) || ! app()->bound(self::BEE_HIVE_TENANT_CONTEXT)) {
            self::markTestSkipped('BeeHive is not installed in this environment.');
        }

        $user = FakeTenantUser::query()->create([
            'name' => 'Tenant Ada',
            'tenant_id' => 100,
        ]);

        $issued = DomainToken::issue('tenant_user', $user, ['tenant-users.read']);

        // Simulate a different active tenant in the context
        app(self::BEE_HIVE_TENANT_CONTEXT)->set(999);

        $response = $this->getJson('/tenant-secured', [
            'Authorization' => 'Bearer ' . $issued->plainTextToken,
        ]);

        $response->assertStatus(401);
    }
}
