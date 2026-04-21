# Open Questions & Assumptions

This file collects assumptions made during documentation generation and open questions that a human maintainer should review and resolve.

---

## Deployment

### Assumption D-1: Host application database

**Assumed:** The host application provides a working database connection compatible with Laravel's Eloquent and Schema Builder (MySQL, PostgreSQL, or SQLite). The package does not prescribe a specific engine.

**Why it matters:** The migration file uses generic Eloquent Schema Builder syntax, which is cross-driver. However, if the host uses a non-standard driver, the `PRAGMA table_info` call in `DomainTokenFlowTest` (which is SQLite-specific) may not reflect real production behavior.

**Question for maintainers:** Is there a preferred/required database engine for production deployments?

---

### Assumption D-2: No queue or cache requirements

**Assumed:** The package requires no queue workers and no cache store. All operations are synchronous database reads and writes.

**Why it matters:** If future versions add async token invalidation or a cache layer for hot-path lookups, this assumption and the deployment instructions will need updating.

---

## API / Configuration

### Assumption A-1: `vendor:publish` tag names

The service provider registers tags `domain-token-auth` and `domain-token-auth:config` for config, and `domain-token-auth` and `domain-token-auth:migrations` for migrations.

**Question for maintainers:** Confirm that the publish tags in the README (`--tag=domain-token-auth:config` and `--tag=domain-token-auth:migrations`) match the intended public interface. The service provider uses `domain-token-auth:config` and `domain-token-auth:migrations` as separate tags in addition to the combined `domain-token-auth` tag.

---

### Assumption A-2: No route registration

**Assumed:** The package intentionally registers no HTTP routes. All route protection is done via the `domain-token` middleware alias applied by the host application.

**Why it matters:** If a future version adds a token rotation or introspection endpoint, route registration would need to be documented.

---

### Open question A-3: Token cleanup / pruning

The `domain_tokens` table will grow unboundedly over time (expired and revoked tokens are never automatically deleted). There is no pruning command in the current codebase.

**Question for maintainers:** Is there a planned Artisan command or scheduled job for pruning expired/revoked tokens? If so, document its schedule and safety conditions (e.g. audit retention requirements).

---

## Monitoring

### Assumption M-1: No built-in logging

**Assumed:** The package does not log authentication failures or issuance events. The host application is responsible for all observability.

**Question for maintainers:** Should the package emit PSR-3 log events (e.g. via `Log::warning()`) for failed authentication attempts? This would make cross-application monitoring easier without requiring host-level wrapping.

---

## Testing

### Open question T-1: `GenerateDomainToken` command not covered by tests

The `domain-token:generate` Artisan command (`src/Console/Commands/GenerateDomainToken.php`) has no dedicated test in the current suite. Edge cases not verified include:

- Invalid `--starts-at` or `--expires-at` date strings.
- Owner not found in the database.
- Owner found but does not implement `TokenOwner`.
- Domain not configured.

**Action needed:** Add a `tests/Feature/GenerateDomainTokenCommandTest.php` using Orchestra Testbench's `artisan()` helper.

---

### Open question T-2: No-expiry token behavior

When `default_ttl_minutes = 0` (or when `resolveDefaultExpiration()` returns `null`) and no `expiresAt` is passed, the token should never expire. This code path is not explicitly tested.

**Action needed:** Add a test for a token with `expires_at = null` confirming it authenticates successfully well past the global TTL.

---

## Business Logic

### Assumption B-1: Revocation is intentionally irreversible

**Assumed from the codebase:** There is no `unrevoke` method. Once `revoked_at` is set, the token is permanently invalid.

**Why it matters:** If any host application has a workflow requiring token re-activation, it must issue a new token. This should be explicitly stated in integration documentation shared with consumers of the package.

---

### Assumption B-2: Morph map support

`DomainTokenManager::resolveTokenOwner()` calls `Relation::getMorphedModel($token->tokenable_type)` to support Laravel morph maps. However, there is no test that verifies behavior when a morph map is in use (i.e., when `tokenable_type` stores a short alias rather than the FQCN).

**Question for maintainers:** Should the package document a requirement for registering owner model classes in the Laravel morph map? Is there a convention for how `tokenable_type` is stored in production deployments using morph maps?

---

### Open question B-3: Token name uniqueness

The `name` column on `domain_tokens` has no unique constraint. Multiple tokens for the same owner and domain may share the same name.

**Question for maintainers:** Is name uniqueness (per owner, per domain) a desired invariant? If so, a unique index and a uniqueness check in `DomainTokenManager::issue()` should be added.

---

## BeeHive Integration

### Assumption BH-1: BeeHive is optional

**Assumed:** `equidna/bee-hive` is listed under `suggest` in `composer.json`, not `require`. All BeeHive-specific code paths are guarded by `class_exists()` and `trait_exists()` checks, so the package is fully functional without BeeHive.

**Question for maintainers:** What is the canonical multi-tenant setup that the package is designed to integrate with? Is `equidna/bee-hive` the only supported tenant context provider, or should a generic contract be considered for future extensibility?
