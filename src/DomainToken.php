<?php

namespace Equidna\DomainTokenAuth;

use DateTimeInterface;
use Equidna\DomainTokenAuth\Auth\AuthenticatedDomainToken;
use Equidna\DomainTokenAuth\DTO\IssuedToken;
use Equidna\DomainTokenAuth\Models\DomainToken as DomainTokenModel;
use Equidna\DomainTokenAuth\Services\DomainTokenManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DomainToken
{
    public function __construct(
        private readonly DomainTokenManager $manager,
        private readonly Request $request
    ) {}

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
        return $this->manager->issue(
            domain: $domain,
            owner: $owner,
            actions: $actions,
            roles: $roles,
            startsAt: $startsAt,
            expiresAt: $expiresAt,
            name: $name,
            data: $data
        );
    }

    public function authenticate(string $plainToken, string $domain): \Equidna\DomainTokenAuth\Auth\AuthenticatedDomainToken
    {
        return $this->manager->authenticate($plainToken, $domain);
    }

    public function revoke(string $plainToken, string $reason = 'manual'): bool
    {
        return $this->manager->revoke($plainToken, $reason);
    }

    public function can(string $action, ?DomainTokenModel $token = null): bool
    {
        $resolved = $token ?? $this->context()?->token;

        if (! $resolved instanceof DomainTokenModel) {
            return false;
        }

        return $this->manager->can($resolved, $action);
    }

    public function context(): ?AuthenticatedDomainToken
    {
        $request = app('request');

        if (! $request instanceof Request) {
            $request = $this->request;
        }

        $context = $request->attributes->get(DomainTokenManager::requestContextKey());

        return $context instanceof AuthenticatedDomainToken ? $context : null;
    }

    public function data(?string $key = null, mixed $default = null): mixed
    {
        $context = $this->context();

        if (! $context) {
            return $key === null ? [] : $default;
        }

        return $context->data($key, $default);
    }
}
