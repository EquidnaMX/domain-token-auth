<?php

namespace Equidna\DomainTokenAuth\Tests;

use Equidna\BeeHive\Traits\BelongsToTenant;
use Equidna\DomainTokenAuth\Concerns\HasDomainTokens;
use Equidna\DomainTokenAuth\Contracts\TokenOwner;
use Illuminate\Database\Eloquent\Model;

class FakeTenantUser extends Model implements TokenOwner
{
    use BelongsToTenant;
    use HasDomainTokens;

    protected $table = 'fake_tenant_users';

    protected $guarded = [];
}
