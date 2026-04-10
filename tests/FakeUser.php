<?php

namespace Equidna\DomainTokenAuth\Tests;

use Equidna\DomainTokenAuth\Concerns\HasDomainTokens;
use Equidna\DomainTokenAuth\Contracts\TokenOwner;
use Illuminate\Database\Eloquent\Model;

class FakeUser extends Model implements TokenOwner
{
    use HasDomainTokens;

    protected $table = 'fake_users';

    protected $guarded = [];
}
