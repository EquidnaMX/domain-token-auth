<?php

namespace Equidna\DomainTokenAuth\Auth;

use Equidna\DomainTokenAuth\Models\DomainToken;
use Illuminate\Database\Eloquent\Model;

class AuthenticatedDomainToken
{
    public function __construct(
        public readonly DomainToken $token,
        public readonly string $domain,
        public readonly ?Model $owner
    ) {
        //
    }

    public function data(?string $key = null, mixed $default = null): mixed
    {
        $payload = (array) ($this->token->data ?? []);

        if ($key === null) {
            return $payload;
        }

        return $payload[$key] ?? $default;
    }
}
