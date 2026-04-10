<?php

namespace Equidna\DomainTokenAuth\Tests;

use Equidna\DomainTokenAuth\Concerns\HasDomainTokens;
use Equidna\DomainTokenAuth\Contracts\TokenOwner;
use Illuminate\Database\Eloquent\Model;

class FakeApplication extends Model implements TokenOwner
{
    use HasDomainTokens;

    protected $table = 'fake_applications';

    protected $guarded = [];
}
