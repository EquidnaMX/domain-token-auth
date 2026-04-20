<?php

namespace Equidna\DomainTokenAuth\Facades;

use Illuminate\Support\Facades\Facade;
use Equidna\DomainTokenAuth\DomainToken as DomainTokenClass;

class DomainToken extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DomainTokenClass::class;
    }
}
