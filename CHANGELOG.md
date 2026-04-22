# Changelog

All notable changes to `equidna/domain-token-auth` are documented in this file.

This file is the **single source of truth** for the project's release history.  
All AI agents and maintainers **must** read and update this file for every release.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added

- Token custom metadata payload support via new optional `data` parameter in `DomainToken::issue()` and `DomainTokenManager::issue()`.
- New migration `2026_04_22_000001_add_data_to_domain_tokens_table.php` adding nullable JSON column `data`.
- Runtime metadata helpers:
  - `DomainToken::context()`
  - `DomainToken::data(?string $key = null, mixed $default = null)`
  - `AuthenticatedDomainToken::data(?string $key = null, mixed $default = null)`
- Artisan option `--data` in `domain-token:generate` for passing JSON metadata.
- Feature tests for metadata persistence and consumption after authentication.

### Changed

- Documentation updated to reflect token metadata support and current API/CLI behavior:
  - `README.md`
  - `doc/deployment-instructions.md`
  - `doc/api-documentation.md`
  - `doc/artisan-commands.md`
  - `doc/tests-documentation.md`
  - `doc/business-logic-and-core-processes.md`
  - `doc/architecture-diagrams.md`

## [v2.0.0] - 2026-04-21 "Boundary"

### Changed

- Authentication now rejects orphan tokens (tokens whose polymorphic owner record no longer exists) with `TokenValidationException("Token owner not found.")` in `DomainTokenManager::authenticate()`.
- `DomainToken` now resolves its table name from `config('domain-token-auth.token.table')`.
- Tenant isolation now compares active BeeHive `TenantContext` directly against owner tenant data at authentication time.

### Removed

- Obsolete BeeHive configuration flag `bee_hive.allow_legacy_tokens_without_tenant_id` (`DOMAIN_TOKEN_ALLOW_LEGACY_TOKENS`).
- Token column `tenant_id` from package migration/model persistence.

### Added

- Feature test `test_rejects_orphan_token_when_owner_is_deleted` in `tests/Feature/DomainTokenFlowTest.php` to prevent regressions.
- Documentation updates to reflect runtime owner enforcement during authentication:
  - `doc/business-logic-and-core-processes.md`
  - `doc/architecture-diagrams.md`
  - `doc/tests-documentation.md`

## [v1.0.0] - 2026-04-20 "Keystone"

First stable release of `equidna/domain-token-auth`.

### Added

#### Core token lifecycle

- `Equidna\DomainTokenAuth\Services\DomainTokenManager` — core service implementing `issue()`, `authenticate()`, `revoke()`, and `can()`.
- `Equidna\DomainTokenAuth\DomainToken` — injectable entry-point wrapping `DomainTokenManager` and the current `Request`.
- `Equidna\DomainTokenAuth\Facades\DomainToken` — Laravel facade registered automatically via package auto-discovery.
- `Equidna\DomainTokenAuth\Providers\DomainTokenAuthServiceProvider` — registers singletons, middleware alias, migrations, config publishing, and the Artisan command.

#### Token model & persistence

- `Equidna\DomainTokenAuth\Models\DomainToken` — Eloquent model with `isRevoked()` and `isWithinWindow()` helpers.
- Migration `2026_04_06_000000_create_domain_tokens_table` creating the `domain_tokens` table with:
  - SHA-256 hash column (`token_hash char(64) UNIQUE`) — plain-text token is never stored.
  - Polymorphic owner columns (`tokenable_type`, `tokenable_id`) — enforced `NOT NULL`.
  - Domain column (`domain varchar(64)`).
  - JSON columns `roles` and `actions`.
  - Validity window columns: `starts_at`, `expires_at`, `last_used_at`.
  - Revocation columns: `revoked_at`, `revoked_reason`.
  - Optional multi-tenant column: `tenant_id`.
  - Composite indexes: `(domain, revoked_at)`, `(domain, starts_at, expires_at)`, `(domain, tokenable_type, tokenable_id)`, `(tenant_id, domain)`.

#### Owner contract & trait

- `Equidna\DomainTokenAuth\Contracts\TokenOwner` — interface that owner models must implement.
- `Equidna\DomainTokenAuth\Concerns\HasDomainTokens` — trait providing the `domainTokens()` `MorphMany` relationship and default `TokenOwner` implementations.

#### DTOs

- `Equidna\DomainTokenAuth\DTO\IssuedToken` — carries `plainTextToken` (shown once) and the persisted `DomainToken` model.
- `Equidna\DomainTokenAuth\Auth\AuthenticatedDomainToken` — carries `token`, `domain`, and resolved `owner` after successful authentication.

#### Middleware

- `Equidna\DomainTokenAuth\Http\Middleware\ValidateDomainToken` — registered as alias `domain-token`. Requires a domain parameter (`domain-token:user`). Returns HTTP 401 JSON on any authentication failure.

#### Action authorization

- `Equidna\DomainTokenAuth\Support\ActionMatcher` — static helper supporting exact matches, domain-level wildcards (`users.*`), and global wildcard (`*`). All comparisons are case-insensitive and trimmed.
- Roles configured per-domain in `config/domain-token-auth.php` are expanded into concrete actions at issuance time.

#### Exceptions

- `Equidna\DomainTokenAuth\Exceptions\DomainTokenException` — base exception extending `RuntimeException`.
- `Equidna\DomainTokenAuth\Exceptions\InvalidDomainException` — thrown when a domain is not configured, the owner model does not match, or the `TokenOwner` contract is not implemented.
- `Equidna\DomainTokenAuth\Exceptions\TokenValidationException` — thrown when a token is not found, revoked, outside its validity window, or violates tenant isolation.

#### Artisan command

- `domain-token:generate` (`Equidna\DomainTokenAuth\Console\Commands\GenerateDomainToken`) — generates a token from the CLI with arguments `domain` and `owner_id`, and options `--roles`, `--actions`, `--name`, `--starts-at`, `--expires-at`.

#### Configuration

- `config/domain-token-auth.php` — publishable configuration defining:
  - `token.prefix`, `token.length`, `token.default_ttl_minutes`, `token.table`.
  - `domains.*` — per-domain `model`, `default_actions`, `roles`, `default_ttl_minutes`.
  - `bee_hive.*` — BeeHive multi-tenant integration options.

#### BeeHive multi-tenant integration (optional)

- `tenant_id` stored on each token at issuance time, sourced from the owner model's `BelongsToTenant` trait or the active `TenantContext`.
- Tenant isolation enforcement at authentication time (`bee_hive.enforce_tenant_isolation`): rejects tokens whose `tenant_id` does not match the active `TenantContext`.
- Automatic `TenantContext` propagation after successful authentication (`bee_hive.apply_tenant_context`).
- `bee_hive.allow_legacy_tokens_without_tenant_id` flag for backward compatibility with tokens issued before `tenant_id` support.
- Environment variables: `DOMAIN_TOKEN_APPLY_TENANT_CONTEXT`, `DOMAIN_TOKEN_ENFORCE_TENANT_ISOLATION`, `DOMAIN_TOKEN_ALLOW_LEGACY_TOKENS`.

#### Tests

- Feature test suite `DomainTokenFlowTest` covering: issuance, middleware authentication, revocation, validity window enforcement, domain isolation, role-to-action expansion, BeeHive tenant context propagation, and tenant isolation enforcement.
- Unit test suite `ActionMatcherTest` covering: exact match, global wildcard, domain wildcard, denial, CSV parsing normalization.
- Test infrastructure: `FakeUser`, `FakeApplication`, `FakeTenantUser` models; abstract `TestCase` with Orchestra Testbench and in-memory SQLite.

#### Documentation

- `README.md` — project overview, tech summary, quick-start guide, and documentation index.
- `doc/deployment-instructions.md` — system requirements, install steps, config reference, migration schema, and deployment workflow.
- `doc/api-documentation.md` — full PHP API surface: facade methods, middleware, contracts, and exception hierarchy.
- `doc/routes-documentation.md` — middleware registration, host-application usage patterns, and authentication decision flow.
- `doc/artisan-commands.md` — `domain-token:generate` argument/option reference and usage examples.
- `doc/tests-documentation.md` — test framework, runner, structure, per-test descriptions, and conventions.
- `doc/architecture-diagrams.md` — C4 system context, container, component, and sequence diagrams (Mermaid).
- `doc/monitoring.md` — logging guidance, metrics, alerts, and troubleshooting.
- `doc/business-logic-and-core-processes.md` — core process flows with Mermaid diagrams and business invariants.
- `doc/open-questions-and-assumptions.md` — documented assumptions and open questions for maintainers.

---

<!-- Links section — update with each release -->

[v2.0.0]: https://github.com/EquidnaMX/domain-token-auth/releases/tag/v2.0.0
[v1.0.0]: https://github.com/EquidnaMX/domain-token-auth/releases/tag/1.0.0
[Unreleased]: https://github.com/EquidnaMX/domain-token-auth/compare/v2.0.0...HEAD
