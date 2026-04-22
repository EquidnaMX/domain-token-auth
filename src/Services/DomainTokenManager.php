<?php

namespace Equidna\DomainTokenAuth\Services;

use DateTimeInterface;
use Equidna\DomainTokenAuth\Auth\AuthenticatedDomainToken;
use Equidna\DomainTokenAuth\Contracts\TokenOwner;
use Equidna\DomainTokenAuth\DTO\IssuedToken;
use Equidna\DomainTokenAuth\Exceptions\InvalidDomainException;
use Equidna\DomainTokenAuth\Exceptions\TokenValidationException;
use Equidna\DomainTokenAuth\Models\DomainToken;
use Equidna\DomainTokenAuth\Support\ActionMatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DomainTokenManager
{
    private const REQUEST_CONTEXT_KEY = '_domain_token_auth_context';

    public function issue(
        string $domain,
        Model $owner,
        array $actions = [],
        array $roles = [],
        ?DateTimeInterface $startsAt = null,
        ?DateTimeInterface $expiresAt = null,
        ?string $name = null,
        array $data = []
    ): IssuedToken {
        $domainConfig = $this->resolveDomainConfig($domain);
        $this->assertModelAllowedForDomain($owner, $domainConfig['model']);
        $this->assertTokenOwnerContract($owner);

        $tokenConfig = (array) Config::get('domain-token-auth.token', []);
        $tokenLength = (int) Arr::get($tokenConfig, 'length', 64);
        $tokenPrefix = (string) Arr::get($tokenConfig, 'prefix', 'dtk_');

        $plainToken = $tokenPrefix . Str::random($tokenLength);
        $tokenHash  = hash('sha256', $plainToken);

        $resolvedStartsAt  = $startsAt ? Carbon::instance(Carbon::parse($startsAt)) : Carbon::now();
        $resolvedExpiresAt = $expiresAt ? Carbon::instance(Carbon::parse($expiresAt)) : $this->resolveDefaultExpiration($domainConfig);

        $grantedActions  = $this->resolveActions($roles, $actions, $domainConfig);
        $normalizedRoles = array_values(array_unique(array_filter(array_map(
            fn(string $role): string => strtolower(trim($role)),
            $roles
        ))));

        $normalizedData = $this->normalizeData($data);


        $token = DB::transaction(function () use (
            $tokenHash,
            $domain,
            $name,
            $normalizedRoles,
            $grantedActions,
            $normalizedData,
            $owner,
            $resolvedStartsAt,
            $resolvedExpiresAt
        ): DomainToken {
            return DomainToken::query()->create([
                'token_hash' => $tokenHash,
                'domain' => $domain,
                'name' => $name,
                'roles' => $normalizedRoles,
                'actions' => $grantedActions,
                'data' => $normalizedData,
                'tokenable_type' => $owner->getMorphClass(),
                'tokenable_id' => (string) $owner->getKey(),
                'starts_at' => $resolvedStartsAt,
                'expires_at' => $resolvedExpiresAt,
            ]);
        });

        return new IssuedToken($plainToken, $token);
    }

    public function authenticate(string $plainToken, string $domain): AuthenticatedDomainToken
    {
        if ($plainToken === '') {
            throw new TokenValidationException('Token is empty.');
        }

        $this->resolveDomainConfig($domain);

        $tokenHash = hash('sha256', $plainToken);
        /** @var DomainToken|null $token */
        $token = DomainToken::query()
            ->where('token_hash', $tokenHash)
            ->where('domain', $domain)
            ->first();

        if (! $token) {
            throw new TokenValidationException('Token not found.');
        }

        if ($token->isRevoked()) {
            throw new TokenValidationException('Token revoked.');
        }

        $now = Carbon::now();
        if (! $token->isWithinWindow($now)) {
            throw new TokenValidationException('Token out of validity window.');
        }

        $token->forceFill(['last_used_at' => $now])->save();

        $owner = $this->resolveTokenOwner($token);
        if (! $owner) {
            throw new TokenValidationException('Token owner not found.');
        }

        $this->assertTenantIsolation($token, $owner);
        $this->applyTenantContextFromOwner($owner);

        return new AuthenticatedDomainToken($token, $domain, $owner);
    }

    public function revoke(string $plainToken, string $reason = 'manual'): bool
    {
        $tokenHash = hash('sha256', $plainToken);
        $token = DomainToken::query()->where('token_hash', $tokenHash)->first();

        if (! $token || $token->isRevoked()) {
            return false;
        }

        $token->forceFill([
            'revoked_at' => Carbon::now(),
            'revoked_reason' => $reason,
        ])->save();

        return true;
    }

    public function can(DomainToken $token, string $action): bool
    {
        if ($token->isRevoked()) {
            return false;
        }

        if (! $token->isWithinWindow(Carbon::now())) {
            return false;
        }

        return ActionMatcher::allows((array) $token->actions, $action);
    }

    public static function requestContextKey(): string
    {
        return self::REQUEST_CONTEXT_KEY;
    }

    private function resolveDomainConfig(string $domain): array
    {
        $domains = (array) Config::get('domain-token-auth.domains', []);

        if (! array_key_exists($domain, $domains)) {
            throw new InvalidDomainException(sprintf('Domain "%s" is not configured.', $domain));
        }

        $domainConfig = (array) $domains[$domain];
        if (! isset($domainConfig['model']) || ! is_string($domainConfig['model'])) {
            throw new InvalidDomainException(sprintf('Domain "%s" has no valid model configured.', $domain));
        }

        return $domainConfig;
    }

    private function assertModelAllowedForDomain(Model $owner, string $expectedModelClass): void
    {
        if (! $owner instanceof $expectedModelClass) {
            throw new InvalidDomainException(sprintf(
                'Model "%s" is not valid for expected domain model "%s".',
                $owner::class,
                $expectedModelClass
            ));
        }
    }

    private function assertTokenOwnerContract(Model $owner): void
    {
        if (! $owner instanceof TokenOwner) {
            throw new InvalidDomainException(sprintf(
                'Model "%s" must implement %s.',
                $owner::class,
                TokenOwner::class
            ));
        }
    }

    private function resolveDefaultExpiration(array $domainConfig): ?Carbon
    {
        $domainTtl = Arr::get($domainConfig, 'default_ttl_minutes');
        if (is_numeric($domainTtl) && (int) $domainTtl > 0) {
            return Carbon::now()->addMinutes((int) $domainTtl);
        }

        $globalTtl = (int) Config::get('domain-token-auth.token.default_ttl_minutes', 60);

        return $globalTtl > 0 ? Carbon::now()->addMinutes($globalTtl) : null;
    }

    private function resolveActions(array $roles, array $requestedActions, array $domainConfig): array
    {
        $domainRolesMap = (array) Arr::get($domainConfig, 'roles', []);
        $defaultActions = array_map(
            fn(string $action): string => ActionMatcher::normalize($action),
            (array) Arr::get($domainConfig, 'default_actions', [])
        );

        $roleActions = [];
        foreach ($roles as $role) {
            $normalizedRole = strtolower(trim($role));
            $mappedActions = (array) ($domainRolesMap[$normalizedRole] ?? []);

            foreach ($mappedActions as $action) {
                if (is_string($action)) {
                    $roleActions[] = ActionMatcher::normalize($action);
                }
            }
        }

        $normalizedRequested = array_map(
            fn(string $action): string => ActionMatcher::normalize($action),
            $requestedActions
        );

        $actions = array_values(array_unique(array_filter([
            ...$defaultActions,
            ...$roleActions,
            ...$normalizedRequested,
        ])));

        return $actions;
    }

    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $trimmedKey = trim($key);
            if ($trimmedKey === '') {
                continue;
            }

            $normalized[$trimmedKey] = $value;
        }

        return $normalized;
    }


    private function assertTenantIsolation(DomainToken $token, ?Model $owner): void
    {
        if (! Config::get('domain-token-auth.bee_hive.enforce_tenant_isolation', true)) {
            return;
        }

        $tenantContextClass = 'Equidna\\BeeHive\\Tenancy\\TenantContext';
        $belongsToTenantClass = 'Equidna\\BeeHive\\Traits\\BelongsToTenant';

        if (! class_exists($tenantContextClass) || ! app()->bound($tenantContextClass)) {
            return;
        }
        if (! $owner || ! trait_exists($belongsToTenantClass) || ! in_array($belongsToTenantClass, class_uses_recursive($owner), true)) {
            return;
        }

        /** @var \\Equidna\\BeeHive\\Tenancy\\TenantContext $context */
        $context = app($tenantContextClass);
        $tenantKey = method_exists($owner, 'getTenantKeyName')
            ? $owner->getTenantKeyName()
            : (string) Config::get('bee-hive.tenant_key', 'tenant_id');
        $ownerTenantId = $owner->getAttribute($tenantKey);

        if ($context->has()) {
            // Regla 1: Si hay tenant activo, debe coincidir con el del owner
            if ($ownerTenantId === null || (string)$context->get() !== (string)$ownerTenantId) {
                throw new TokenValidationException('Token tenant mismatch.');
            }
        }
        // Si no hay tenant activo, no se lanza excepción aquí (la asignación de contexto es en applyTenantContextFromOwner)
    }

    private function resolveTokenOwner(DomainToken $token): ?Model
    {
        $modelClass = Relation::getMorphedModel($token->tokenable_type) ?? $token->tokenable_type;

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        return $modelClass::withoutGlobalScopes()->whereKey($token->tokenable_id)->first();
    }

    private function applyTenantContextFromOwner(?Model $owner): void
    {
        if (! $owner) {
            return;
        }
        if (! Config::get('domain-token-auth.bee_hive.apply_tenant_context', true)) {
            return;
        }
        $belongsToTenantClass = 'Equidna\\BeeHive\\Traits\\BelongsToTenant';
        $tenantContextClass    = 'Equidna\\BeeHive\\Tenancy\\TenantContext';
        if (! trait_exists($belongsToTenantClass) || ! in_array($belongsToTenantClass, class_uses_recursive($owner), true)) {
            return;
        }
        if (! app()->bound($tenantContextClass)) {
            return;
        }
        /** @var \Equidna\BeeHive\Tenancy\TenantContext $context */
        $context = app($tenantContextClass);
        $tenantKey = method_exists($owner, 'getTenantKeyName')
            ? $owner->getTenantKeyName()
            : (string) Config::get('bee-hive.tenant_key', 'tenant_id');
        $ownerTenantId = $owner->getAttribute($tenantKey);
        if (! $context->has() && $ownerTenantId !== null) {
            // Regla 2: Si no hay tenant activo y el owner tiene tenant_id, lo establecemos
            $context->set($ownerTenantId);
        }
        // Regla 3: Si no existe tenant_id en el owner, no se establece contexto
    }
}
