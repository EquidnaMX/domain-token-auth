# Breaking Changes

This file documents **breaking changes** in `equidna/domain-token-auth` between versions and provides migration guidance.

All AI agents and maintainers **must** update this file whenever a release introduces a breaking change.

---

## v2.1.0

No breaking changes were introduced in `v2.1.0`.

This release is backward-compatible with `v2.0.0` and only adds optional token metadata support (`data` payload), helper accessors, and test/runtime quality improvements.

---

## v2.0.0

### 1. `tenant_id` removed from token persistence

What changed:

- Package migration no longer creates `tenant_id` on token table.
- Package model no longer persists/reads token-level `tenant_id`.

Why:

- Tenant identity is now derived from the resolved owner at authentication time.
- This avoids duplicated tenant state between token row and owner row.

Migration steps:

1. Remove any host-app query logic that filters token rows by `tenant_id`.
2. If you previously added a custom `tenant_id` index/query path, replace it with owner-based checks.
3. If your production database still has `tenant_id`, keep it only as legacy data or drop it with a host-app migration after validating no code references remain.

### 2. Legacy BeeHive config key removed

What changed:

- Removed config key `bee_hive.allow_legacy_tokens_without_tenant_id`.
- Removed env var `DOMAIN_TOKEN_ALLOW_LEGACY_TOKENS`.

Why:

- There is no longer token-level `tenant_id` compatibility mode to control.

Migration steps:

1. Remove `DOMAIN_TOKEN_ALLOW_LEGACY_TOKENS` from `.env` and deployment templates.
2. Remove references to `bee_hive.allow_legacy_tokens_without_tenant_id` from host configuration overrides.

### 3. Authentication now rejects orphan tokens

What changed:

- Tokens whose polymorphic owner cannot be resolved are now rejected with `TokenValidationException("Token owner not found.")`.

Why:

- Prevents authentication for tokens that no longer map to a valid owner record.

Migration steps:

1. Ensure owner lifecycle policies handle token cleanup or revocation when owners are deleted.
2. Expect HTTP 401 for orphan tokens in protected routes and update client handling accordingly.

---

## v1.0.0 — Initial Stable Release

`v1.0.0` is the first stable release. There are no prior stable versions to break compatibility with.

This section documents the **baseline contracts** that future releases must treat as the stable API surface. Any change to these contracts in a future release is a **breaking change** and must be documented here.

---

### Stable API Contracts established in v1.0.0

#### `TokenOwner` interface (`Equidna\DomainTokenAuth\Contracts\TokenOwner`)

```php
interface TokenOwner
{
    public function getTokenOwnerIdentifier(): string;
    public function getTokenOwnerDisplayName(): ?string;
}
```

**Contract:** Any model passed as an owner to `DomainTokenManager::issue()` must implement this interface. A future version removing or renaming either method is a **breaking change**.

---

#### `DomainToken` / Facade public methods

| Method         | Signature                                                                                                                                                                                        |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `issue`        | `issue(string $domain, TokenOwner $owner, array $roles = [], array $actions = [], ?string $name = null, ?DateTimeInterface $startsAt = null, ?DateTimeInterface $expiresAt = null): IssuedToken` |
| `authenticate` | `authenticate(string $domain, string $plainToken): AuthenticatedDomainToken`                                                                                                                     |
| `revoke`       | `revoke(DomainToken $token, ?string $reason = null): void`                                                                                                                                       |
| `can`          | `can(string $action, ?AuthenticatedDomainToken $context = null): bool`                                                                                                                           |

Any change to method names, parameter order, or return types is a **breaking change**.

---

#### Middleware alias

The middleware must remain registered under the alias `domain-token` and must accept the domain as its first parameter:

```php
Route::middleware('domain-token:user')
```

---

#### Token format

Token plain-text format: `dtk_` followed by 64 random characters. The prefix `dtk_` is part of the public contract (e.g., consumers may strip/validate the prefix). Changing the prefix or length is a **breaking change**.

---

#### Database table and column names

The `domain_tokens` table and its column names are part of the public contract because host applications may query or reference them directly. Renaming columns or the table is a **breaking change** requiring a migration.

---

#### Config keys

The following `config/domain-token-auth.php` keys are stable:

```
token.prefix
token.length
token.table
token.default_ttl_minutes
domains.{name}.model
domains.{name}.roles
domains.{name}.default_actions
domains.{name}.default_ttl_minutes
bee_hive.apply_tenant_context
bee_hive.enforce_tenant_isolation
bee_hive.allow_legacy_tokens_without_tenant_id
```

Renaming or removing any of these keys is a **breaking change**.

---

> **Note for future AI agents:** Before releasing a version that modifies any of the contracts above, add a new `## vX.Y.Z` section to this file with a description of what changed, why, and a migration path for consumers.
