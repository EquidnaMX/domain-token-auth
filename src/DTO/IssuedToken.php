<?php

namespace Equidna\DomainTokenAuth\DTO;

use Equidna\DomainTokenAuth\Models\DomainToken;

class IssuedToken
{
    public function __construct(
        public readonly string $plainTextToken,
        public readonly DomainToken $token
    ) {}
}
