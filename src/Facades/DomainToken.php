<?php

namespace Equidna\DomainTokenAuth\Facades;

use Illuminate\Support\Facades\Facade;

class DomainToken extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Equidna\DomainTokenAuth\DomainToken::class;
    }
}
