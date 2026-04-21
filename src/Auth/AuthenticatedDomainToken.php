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
}
