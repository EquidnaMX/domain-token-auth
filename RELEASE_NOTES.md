# Release v2.3.0

**Package:** `equidna/domain-token-auth`

**Date:** 2026-08-26

**Tag:** `2.3.0`

---

## Summary

`v2.3.0` expands framework compatibility to Laravel 13 and Symfony 8 while preserving support for Laravel 12, PHP 8.2+, and Symfony 7. The package API and database schema are unchanged.

This release also adds continuous-integration coverage for the supported framework combinations and a runtime compatibility test that confirms the service provider and core package services boot correctly.

---

## Highlights

- Laravel 13 support across the package's Illuminate dependencies.
- Symfony HttpFoundation 8 support alongside Symfony 7.2+.
- Orchestra Testbench 11 and PHPUnit 12/13 support for development and CI.
- CI coverage for PHP 8.2, 8.3, and 8.4 framework stacks.
- No application code, configuration, or database migration changes required.

---

## Added

- GitHub Actions test matrix for these representative stacks:
  - PHP 8.2, Testbench 10, and Symfony 7.2.
  - PHP 8.3, Testbench 11, and Symfony 7.4.
  - PHP 8.4, Testbench 11, and Symfony 8.
- `FrameworkCompatibilityTest` to verify that the package service provider boots and the `DomainToken` entry point resolves from the container.

## Changed

- Illuminate component constraints now accept `^12.0|^13.0`.
- `symfony/http-foundation` is now an explicit dependency with constraint `^7.2|^8.0`.
- Development constraints now accept Orchestra Testbench `^10.0|^11.0` and PHPUnit `^11.5.3|^12.0.1|^13.0.0`.
- Documentation now describes the PHP, Laravel, and Symfony compatibility combinations.
- Locked development dependencies were refreshed against the Laravel 13 / Symfony 8 stack.

---

## Breaking Changes

None in `v2.3.0`. Laravel 12 remains supported, and the public API and persistence schema are unchanged.

See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for historical migration notes.

---

## Upgrade Guide

1. Update the package constraint and dependencies:

```bash
composer require equidna/domain-token-auth:^2.3
```

2. No package migrations or configuration changes are required for this release.
3. When using Symfony 8 components, run the application on PHP 8.4 or newer. Laravel 12 installations may continue using PHP 8.2+ with Symfony 7.

---

## References

- Full history: [CHANGELOG.md](CHANGELOG.md)
- Breaking changes and migration guidance: [BREAKING_CHANGES.md](BREAKING_CHANGES.md)
- Deployment guide: [doc/deployment-instructions.md](doc/deployment-instructions.md)
- Test documentation: [doc/tests-documentation.md](doc/tests-documentation.md)
