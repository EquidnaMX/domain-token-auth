# Release v1.0.0 "Keystone"

**Package:** `equidna/domain-token-auth`  
**Date:** 2026-04-20  
**Tag:** `v1.0.0`

---

## Summary

`equidna/domain-token-auth` `v1.0.0` is the first stable, production-ready release of the Equidna domain-scoped token authentication library for Laravel.

The codename **Keystone** reflects the role this package plays in the Equidna ecosystem: it is the foundational authentication layer on which higher-level services are built. A keystone is the central stone of an arch — remove it and everything collapses; get it right and everything holds.

This release delivers a complete, zero-breaking-change API for issuing, authenticating, revoking, and authorizing opaque tokens that are scoped to named functional domains. It ships with optional first-class integration with `equidna/bee-hive` for multi-tenant isolation, and with comprehensive documentation covering deployment, architecture, API, business logic, and monitoring.

---

## Highlights

- **Domain-scoped opaque tokens** — tokens are issued per named domain (e.g., `user`, `app`) and are strictly rejected outside their domain.
- **SHA-256 only storage** — the plain-text token (`dtk_` + 64 random characters) is returned exactly once; only the hash is persisted.
- **Fine-grained action authorization** — `ActionMatcher` supports exact actions, domain wildcards (`users.*`), and global wildcard (`*`), normalized and case-insensitive.
- **Role-to-action expansion** — domain roles defined in config are expanded to concrete action lists at issuance time.
- **Validity window enforcement** — `starts_at` / `expires_at` optional window checked at authentication time; `last_used_at` is updated on every successful authentication.
- **Soft revocation** — tokens are revoked by writing `revoked_at`; revocation is irreversible by design.
- **Polymorphic owner** — any Eloquent model implementing `TokenOwner` (via `HasDomainTokens` trait) can own tokens.
- **BeeHive multi-tenant integration** (optional) — tenant isolation enforced at authentication time; `TenantContext` automatically propagated after successful auth.
- **Laravel middleware** — `domain-token:{domain}` middleware protects route groups with a single line.
- **Artisan command** — `domain-token:generate` for CLI token issuance (scripting, onboarding, CI pipelines).
- **Production-grade documentation** — 9 dedicated doc files covering deployment, API, architecture, business logic, monitoring, and open questions.

---

## Added

### Core Token Lifecycle

- `DomainTokenManager` service: `issue()`, `authenticate()`, `revoke()`, `can()`.
- `DomainToken` facade and injectable entry-point class.
- Service provider with auto-discovery, singleton registration, and asset publishing.

### Persistence

- `domain_tokens` database table with SHA-256 hash storage, polymorphic owner, validity window, revocation, roles/actions (JSON), and optional `tenant_id`.
- Four composite database indexes tuned for middleware-path queries.

### Middleware & API

- `ValidateDomainToken` middleware (`domain-token:{domain}`) — JSON 401 on failure.
- Full `Authorization: Bearer` header parsing.

### Authorization

- `ActionMatcher` utility with wildcard support.
- Role-to-action expansion at issuance time via domain config.

### Multi-tenant Support (BeeHive integration)

- `tenant_id` stored and validated per token.
- Configurable: `enforce_tenant_isolation`, `apply_tenant_context`, `allow_legacy_tokens_without_tenant_id`.

### CLI

- `domain-token:generate` Artisan command with full argument and option set.

### Tests

- 11 tests / 11 assertions — Feature and Unit suites using Orchestra Testbench + in-memory SQLite.

### Documentation

- `README.md`, `doc/deployment-instructions.md`, `doc/api-documentation.md`, `doc/routes-documentation.md`, `doc/artisan-commands.md`, `doc/tests-documentation.md`, `doc/architecture-diagrams.md`, `doc/monitoring.md`, `doc/business-logic-and-core-processes.md`, `doc/open-questions-and-assumptions.md`.

---

## Breaking Changes

None — this is the initial stable release. See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for baseline contracts.

---

## Upgrade / Migration Guide

This is the first stable release. No upgrade path required.  
For installation and setup, see [doc/deployment-instructions.md](doc/deployment-instructions.md).

---

## References

- Full history: [CHANGELOG.md](CHANGELOG.md)
- Breaking changes & contracts: [BREAKING_CHANGES.md](BREAKING_CHANGES.md)
- Deployment guide: [doc/deployment-instructions.md](doc/deployment-instructions.md)
- API reference: [doc/api-documentation.md](doc/api-documentation.md)
