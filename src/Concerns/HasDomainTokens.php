<?php

namespace Equidna\DomainTokenAuth\Concerns;

use Equidna\DomainTokenAuth\Models\DomainToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDomainTokens
{
    public function domainTokens(): MorphMany
    {
        return $this->morphMany(DomainToken::class, 'tokenable');
    }

    public function getTokenOwnerIdentifier(): string
    {
        return (string) $this->getKey();
    }

    public function getTokenOwnerDisplayName(): ?string
    {
        return property_exists($this, 'name') ? $this->name : null;
    }
}
