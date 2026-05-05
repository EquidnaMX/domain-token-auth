<?php

namespace Equidna\DomainTokenAuth\Tests;

use Equidna\DomainTokenAuth\Concerns\HasDomainTokens;
use Equidna\DomainTokenAuth\Contracts\TokenOwner;
use Illuminate\Database\Eloquent\Model;

class FakeStringKeyUser extends Model implements TokenOwner
{
    use HasDomainTokens;

    protected $table = 'fake_string_key_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
