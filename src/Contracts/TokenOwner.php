<?php

namespace Equidna\DomainTokenAuth\Contracts;

interface TokenOwner
{
    public function getTokenOwnerIdentifier(): string;
    public function getTokenOwnerDisplayName(): ?string;
}
