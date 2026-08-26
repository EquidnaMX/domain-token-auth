# Tests Documentation

---

## Framework & Runner

| Property       | Value                             |
| -------------- | --------------------------------- |
| **Framework**  | PHPUnit `^11.5.3\|^12.0.1\|^13.0` |
| **Test bench** | Orchestra Testbench `^10.0\|^11.0` |
| **Config**     | `phpunit.xml` at project root     |

---

## How to Run Tests

```bash
# Using the Composer script
composer test

# Or directly
vendor/bin/phpunit
```

The test suite uses an in-memory SQLite database. No external database or services need to be configured before running tests.

CI runs the suite against Laravel 12 / Symfony 7, Laravel 13 / Symfony 7.4, and Laravel 13 / Symfony 8. The Symfony 8 job uses PHP 8.4 because Symfony 8 does not support earlier PHP versions.

> Note: BeeHive is optional. If `equidna/bee-hive` is not installed, the tenant-specific feature test is skipped intentionally, so a result like `Skipped: 1` is expected.

---

## Test Structure

```
tests/
├── TestCase.php               # Abstract base class for all test cases
├── FakeUser.php               # Fake owner model (no tenant)
├── FakeApplication.php        # Fake owner model for the 'app' domain
├── FakeTenantUser.php         # Fake owner model with BelongsToTenant
├── Feature/
│   └── DomainTokenFlowTest.php  # End-to-end token lifecycle tests
└── Unit/
    └── ActionMatcherTest.php    # Unit tests for action matching logic
```

---

## Base Test Class

**`Equidna\DomainTokenAuth\Tests\TestCase`** (`tests/TestCase.php`)

Extends `Orchestra\Testbench\TestCase`. Responsibilities:

- Configures an in-memory SQLite connection.
- Registers `DomainTokenAuthServiceProvider` and conditionally registers `BeeHiveServiceProvider` when BeeHive is available.
- Defines three test domains (`user`, `app`, `tenant_user`) pointing at the fake models.
- Runs the package migration (`domain_tokens` table).
- Creates in-memory tables for `fake_users`, `fake_applications`, and `fake_tenant_users`.
- Registers three test routes:
  - `GET /secured` — protected by `domain-token:user`
  - `GET /secured-data` — protected by `domain-token:user`; returns token data via facade helpers
  - `GET /tenant-secured` — protected by `domain-token:tenant_user`; returns the active tenant ID from `TenantContext`.

---

## Fake Models

| Class             | File                        | Purpose                                                             |
| ----------------- | --------------------------- | ------------------------------------------------------------------- |
| `FakeUser`        | `tests/FakeUser.php`        | Simple owner model; implements `TokenOwner`, uses `HasDomainTokens` |
| `FakeApplication` | `tests/FakeApplication.php` | Owner model for the `app` domain                                    |
| `FakeTenantUser`  | `tests/FakeTenantUser.php`  | Owner model with `BelongsToTenant` for BeeHive integration tests    |

---

## Feature Tests: `DomainTokenFlowTest`

`tests/Feature/DomainTokenFlowTest.php`

Covers the full token lifecycle through real HTTP-style requests via Orchestra Testbench's `getJson()` / `postJson()` helpers.

| Test                                                    | What it verifies                                                                                                                  |
| ------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `test_domain_tokens_require_an_owner`                   | `tokenable_type` and `tokenable_id` columns are NOT NULL, and `tokenable_id` is stored as `varchar(64)` in the schema             |
| `test_can_issue_and_authenticate_token_for_domain`      | A token issued for `user` authenticates successfully on `GET /secured`                                                            |
| `test_persists_additional_token_data_on_issue`          | Issued token persists custom JSON payload in `data`                                                                               |
| `test_can_consume_token_data_after_authentication`      | Middleware-authenticated request can read token metadata using `DomainToken::data()`                                              |
| `test_role_mapped_actions_allow_authorization_checks`   | A token issued with role `admin` grants `users.delete` via `DomainToken::can()`                                                   |
| `test_rejects_token_with_wrong_domain`                  | A token issued for domain `app` is rejected on a route protected by `domain-token:user`                                           |
| `test_rejects_revoked_token`                            | A revoked token returns HTTP 401                                                                                                  |
| `test_rejects_token_before_start_window`                | A token with `startsAt` in the future returns HTTP 401                                                                            |
| `test_rejects_token_after_expiration`                   | A token with `expiresAt` in the past returns HTTP 401                                                                             |
| `test_rejects_orphan_token_when_owner_is_deleted`       | A token becomes invalid when its owner record is deleted and authentication returns HTTP 401                                      |
| `test_rejects_token_when_context_tenant_does_not_match` | Token is rejected when the active `TenantContext` tenant differs from the owner's tenant_id (skipped when BeeHive is unavailable) |

---

## Unit Tests: `ActionMatcherTest`

`tests/Unit/ActionMatcherTest.php`

Directly tests `Equidna\DomainTokenAuth\Support\ActionMatcher` (`src/Support/ActionMatcher.php`).

| Test                               | What it verifies                                       |
| ---------------------------------- | ------------------------------------------------------ |
| `test_allows_exact_action`         | `allows(['users.read'], 'users.read')` returns `true`  |
| `test_allows_global_wildcard`      | `allows(['*'], 'users.delete')` returns `true`         |
| `test_allows_domain_wildcard`      | `allows(['users.*'], 'users.write')` returns `true`    |
| `test_denies_ungranted_action`     | `allows(['users.read'], 'apps.read')` returns `false`  |
| `test_parse_csv_normalizes_values` | CSV parsing deduplicates and lowercases action strings |

---

## Coverage Assessment

**Well tested:**

- Token issuance, authentication, revocation.
- Validity window enforcement (`starts_at`, `expires_at`).
- Domain isolation (wrong domain rejection).
- Orphan token rejection when the owner no longer exists.
- Role-to-action expansion.
- BeeHive tenant isolation enforcement (context mismatch rejection).
- Action matching (exact, global wildcard, domain wildcard, denial).

**Weakly or not tested:**

- `GenerateDomainToken` Artisan command (no dedicated test; see [Open Questions & Assumptions](open-questions-and-assumptions.md)).
- Token with `default_ttl_minutes = 0` (no expiry).
- `DomainToken::revoke()` called on a token that does not exist (edge-case; covered by the `revoke()` return value contract but not explicitly tested).
- Morph map resolution via `Relation::getMorphedModel()`.

---

## Adding New Tests

### Naming conventions

- Feature tests go in `tests/Feature/` and extend `Equidna\DomainTokenAuth\Tests\TestCase`.
- Pure unit tests go in `tests/Unit/` and extend `PHPUnit\Framework\TestCase` directly (no database or framework needed).
- Test method names use `snake_case` and begin with `test_`.

### Folder placement

Match the `tests/` structure to the `src/` structure where applicable. A new service class in `src/Services/` should have its unit tests in `tests/Unit/Services/`.

### Fake models

If a new test domain requires a new fake model, add it to the `tests/` root following the pattern of `FakeUser.php`.
