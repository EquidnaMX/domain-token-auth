<?php

namespace Equidna\DomainTokenAuth\Tests\Feature;

use Equidna\DomainTokenAuth\Facades\DomainToken;
use Equidna\DomainTokenAuth\Models\DomainToken as DomainTokenModel;
use Equidna\DomainTokenAuth\Tests\FakeStringKeyUser;
use Equidna\DomainTokenAuth\Tests\TestCase;

/**
 * Tests that domain-token-auth works correctly when the token owner has a
 * string primary key ($keyType = 'string', $incrementing = false).
 *
 * Covers: issue, authenticate, revoke, domainTokens() relation, and
 * tokenable_type + tokenable_id queries.
 */
class StringKeyOwnerTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(string $id = 'de86d595b00cd2fd0dcc496c170c303d8bbd6c66'): FakeStringKeyUser
    {
        return FakeStringKeyUser::query()->create([
            'id'   => $id,
            'name' => 'SHA User',
        ]);
    }

    // -----------------------------------------------------------------------
    // Schema assertion
    // -----------------------------------------------------------------------

    public function test_tokenable_id_column_accepts_string_values(): void
    {
        $user   = $this->makeUser();
        $issued = DomainToken::issue('string_key_user', $user, ['string-users.read']);

        $raw = DomainTokenModel::query()->where('id', $issued->token->id)->value('tokenable_id');

        self::assertSame($user->getKey(), $raw);
    }

    // -----------------------------------------------------------------------
    // Issue
    // -----------------------------------------------------------------------

    public function test_can_issue_token_for_string_key_owner(): void
    {
        $user   = $this->makeUser();
        $issued = DomainToken::issue('string_key_user', $user, ['string-users.read']);

        self::assertNotEmpty($issued->plainTextToken);
        self::assertSame((string) $user->getKey(), $issued->token->tokenable_id);
        self::assertSame($user->getMorphClass(), $issued->token->tokenable_type);
    }

    // -----------------------------------------------------------------------
    // Authenticate
    // -----------------------------------------------------------------------

    public function test_can_authenticate_token_for_string_key_owner(): void
    {
        $user   = $this->makeUser();
        $issued = DomainToken::issue('string_key_user', $user, ['string-users.read']);

        $authenticated = DomainToken::authenticate($issued->plainTextToken, 'string_key_user');

        self::assertSame($issued->token->id, $authenticated->token->id);
        self::assertInstanceOf(FakeStringKeyUser::class, $authenticated->token->tokenable);
        self::assertSame($user->getKey(), $authenticated->token->tokenable->getKey());
    }

    // -----------------------------------------------------------------------
    // Revoke
    // -----------------------------------------------------------------------

    public function test_can_revoke_token_for_string_key_owner(): void
    {
        $user   = $this->makeUser();
        $issued = DomainToken::issue('string_key_user', $user, ['string-users.read']);

        DomainToken::revoke($issued->plainTextToken, 'revoked-by-test');

        $this->expectException(\Equidna\DomainTokenAuth\Exceptions\TokenValidationException::class);
        DomainToken::authenticate($issued->plainTextToken, 'string_key_user');
    }

    // -----------------------------------------------------------------------
    // domainTokens() morph relation
    // -----------------------------------------------------------------------

    public function test_domain_tokens_relation_returns_tokens_for_string_key_owner(): void
    {
        $user = $this->makeUser();

        DomainToken::issue('string_key_user', $user, ['string-users.read']);
        DomainToken::issue('string_key_user', $user, ['string-users.read']);

        self::assertCount(2, $user->domainTokens()->get());
    }

    public function test_can_revoke_all_tokens_via_relation_for_string_key_owner(): void
    {
        $user = $this->makeUser();

        $issued1 = DomainToken::issue('string_key_user', $user, ['string-users.read']);
        $issued2 = DomainToken::issue('string_key_user', $user, ['string-users.read']);

        $user->domainTokens()->update(['revoked_at' => now(), 'revoked_reason' => 'bulk-revoke']);

        $token1 = DomainTokenModel::query()->find($issued1->token->id);
        $token2 = DomainTokenModel::query()->find($issued2->token->id);

        self::assertNotNull($token1?->revoked_at);
        self::assertNotNull($token2?->revoked_at);
    }

    // -----------------------------------------------------------------------
    // tokenable_type + tokenable_id queries
    // -----------------------------------------------------------------------

    public function test_can_query_tokens_by_tokenable_type_and_string_id(): void
    {
        $user = $this->makeUser('abc123stringkey');
        DomainToken::issue('string_key_user', $user, ['string-users.read']);

        $tokens = DomainTokenModel::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->get();

        self::assertCount(1, $tokens);
        self::assertSame('abc123stringkey', $tokens->first()->tokenable_id);
    }

    public function test_does_not_cross_contaminate_tokens_between_string_key_owners(): void
    {
        $userA = $this->makeUser('owner-aaa');
        $userB = $this->makeUser('owner-bbb');

        DomainToken::issue('string_key_user', $userA, ['string-users.read']);
        DomainToken::issue('string_key_user', $userB, ['string-users.read']);

        self::assertCount(1, $userA->domainTokens()->get());
        self::assertCount(1, $userB->domainTokens()->get());
    }

    // -----------------------------------------------------------------------
    // Longer string keys (SHA-1 / UUID / ULID)
    // -----------------------------------------------------------------------

    public function test_supports_sha1_length_string_key(): void
    {
        $sha1Id = 'de86d595b00cd2fd0dcc496c170c303d8bbd6c66'; // 40 chars
        $user   = $this->makeUser($sha1Id);
        $issued = DomainToken::issue('string_key_user', $user, ['string-users.read']);

        self::assertSame($sha1Id, $issued->token->tokenable_id);
    }

    public function test_supports_uuid_length_string_key(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000'; // 36 chars
        $user = $this->makeUser($uuid);
        $issued = DomainToken::issue('string_key_user', $user, ['string-users.read']);

        self::assertSame($uuid, $issued->token->tokenable_id);
    }

    public function test_supports_ulid_length_string_key(): void
    {
        $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV'; // 26 chars
        $user = $this->makeUser($ulid);
        $issued = DomainToken::issue('string_key_user', $user, ['string-users.read']);

        self::assertSame($ulid, $issued->token->tokenable_id);
    }
}
