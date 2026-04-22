# Release v2.1.0 "Signal"

**Package:** `equidna/domain-token-auth`  
**Date:** 2026-04-22  
**Tag:** `2.1.0`

---

## Summary

`v2.1.0` is a minor release focused on token metadata and runtime ergonomics.

The codename **Signal** highlights that tokens can now carry explicit, structured context (`data`) that downstream application code can consume safely after authentication.

This release is backward-compatible with `v2.0.0` and includes API additions, a schema evolution for metadata payload, CLI improvements, and stronger test portability for environments where BeeHive is optional.

---

## Highlights

- **Custom token metadata** with optional `data` payload at issuance.
- **Runtime metadata accessors** via `DomainToken::context()` and `DomainToken::data()`.
- **Authenticated context helper** `AuthenticatedDomainToken::data()`.
- **Schema evolution** with JSON `data` column migration.
- **CLI extension** with `domain-token:generate --data='{"key":"value"}'`.
- **Test reliability improvements** in environments without BeeHive.

---

## Added

- Optional `data` parameter in `DomainToken::issue()` and `DomainTokenManager::issue()`.
- Migration `2026_04_22_000001_add_data_to_domain_tokens_table.php` adding nullable JSON `data`.
- Runtime helpers:
  - `DomainToken::context()`
  - `DomainToken::data(?string $key = null, mixed $default = null)`
  - `AuthenticatedDomainToken::data(?string $key = null, mixed $default = null)`
- Artisan command support for `--data` JSON payload.
- Feature tests validating metadata persistence and post-auth metadata consumption.

## Changed

- Testbench setup now handles BeeHive as optional dependency during test execution.
- Tenant mismatch test is conditionally skipped when BeeHive classes are not available.
- Documentation aligned with the current metadata API and CLI surface.

## Fixed

- `DomainToken::data()`/`DomainToken::context()` now resolve from the active request instance to avoid null context reads during authenticated request lifecycle usage.

---

## Breaking Changes

None in `v2.1.0`. See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for historical migration notes.

---

## Upgrade / Migration Guide

1. Pull and install package update.
2. Run migrations to add the `data` column:

```bash
php artisan migrate
```

3. Optionally adopt metadata access in application logic:

```php
$issued = DomainToken::issue(
		domain: 'user',
		owner: $user,
		data: ['client' => 'mobile']
);

$client = DomainToken::data('client');
```

---

## References

- Full history: [CHANGELOG.md](CHANGELOG.md)
- Breaking changes & migration guidance: [BREAKING_CHANGES.md](BREAKING_CHANGES.md)
- API reference: [doc/api-documentation.md](doc/api-documentation.md)
- Deployment guide: [doc/deployment-instructions.md](doc/deployment-instructions.md)
