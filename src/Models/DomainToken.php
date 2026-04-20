<?php

namespace Equidna\DomainTokenAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DomainToken extends Model
{
    protected $table = 'domain_tokens';

    protected $fillable = [
        'token_hash',
        'domain',
        'name',
        'roles',
        'actions',
        'tokenable_type',
        'tokenable_id',
        'tenant_id',
        'starts_at',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'revoked_reason',
    ];

    protected $casts = [
        'roles' => 'array',
        'actions' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isWithinWindow(\DateTimeInterface $at): bool
    {
        if ($this->starts_at && $at < $this->starts_at) {
            return false;
        }

        if ($this->expires_at && $at > $this->expires_at) {
            return false;
        }

        return true;
    }
}
