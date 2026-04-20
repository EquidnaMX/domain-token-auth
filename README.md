# README (Root of the Project)

> This documentation follows the coding conventions reflected throughout the codebase.

---

## Project Overview

**`equidna/domain-token-auth`** is a Laravel package that provides secure, domain-scoped opaque token authentication. It issues cryptographically random tokens tied to a *functional domain*, a polymorphic owner model, a set of roles and fine-grained actions, and an optional validity window. Tokens are stored only as SHA-256 hashes; the plain-text value is shown exactly once at issuance time.

**Who it is for:**

- Laravel application developers who need API token authentication without depending on Laravel Sanctum or Passport.
- Multi-tenant SaaS platforms that must ensure a token issued for one tenant cannot be used in another tenant's context (via optional `equidna/bee-hive` integration).
- Integrators building machine-to-machine token pipelines with per-domain authorization rules.

**Main use cases:**

- Issuing short-lived or long-lived tokens for human users, application clients, services, or partner integrations — each in its own named domain.
- Protecting routes with per-domain middleware (`domain-token:{domain}`).
- Enforcing fine-grained action authorization with wildcard support (`users.*`, `*`).
- Revoking tokens immediately without modifying the consuming request flow.

---

## Project Type & Tech Summary

| Property | Value |
|---|---|
| **Project type** | Laravel **package** (library) |
| **Package name** | `equidna/domain-token-auth` |
| **PHP version** | `^8.2` |
| **Laravel version** | `^12.0` (via `illuminate/*` components) |
| **Primary database** | Relies on the host application's configured database |
| **Cache** | Not used internally |
| **Queue** | Not used internally |
| **Key external service** | `equidna/bee-hive` *(optional)* — multi-tenant context propagation |

---

## Quick Start

1. **Install** via Composer:
   ```bash
   composer require equidna/domain-token-auth
   ```

2. **Publish the configuration** file:
   ```bash
   php artisan vendor:publish --tag=domain-token-auth:config
   ```

3. **Publish and run migrations**:
   ```bash
   php artisan vendor:publish --tag=domain-token-auth:migrations
   php artisan migrate
   ```

4. **Implement `TokenOwner`** on your owner model and add the `HasDomainTokens` trait:
   ```php
   use Equidna\DomainTokenAuth\Concerns\HasDomainTokens;
   use Equidna\DomainTokenAuth\Contracts\TokenOwner;
   use Illuminate\Database\Eloquent\Model;

   class User extends Model implements TokenOwner
   {
       use HasDomainTokens;
   }
   ```

5. **Configure your domains** in `config/domain-token-auth.php`.

6. **Protect routes** with the `domain-token` middleware:
   ```php
   Route::middleware('domain-token:user')->group(function () {
       Route::get('/profile', ProfileController::class);
   });
   ```

7. **Issue tokens** via the facade or Artisan command:
   ```php
   use Equidna\DomainTokenAuth\Facades\DomainToken;

   $issued = DomainToken::issue(domain: 'user', owner: $user, roles: ['admin']);
   $plainToken = $issued->plainTextToken; // show once
   ```

For full details, see [Deployment Instructions](doc/deployment-instructions.md).

---

## Documentation Index

- [Deployment Instructions](doc/deployment-instructions.md)
- [API Documentation](doc/api-documentation.md)
- [Routes Documentation](doc/routes-documentation.md)
- [Artisan Commands](doc/artisan-commands.md)
- [Tests Documentation](doc/tests-documentation.md)
- [Architecture Diagrams](doc/architecture-diagrams.md)
- [Monitoring](doc/monitoring.md)
- [Business Logic & Core Processes](doc/business-logic-and-core-processes.md)
- [Open Questions & Assumptions](doc/open-questions-and-assumptions.md)

---

## License

MIT — see [LICENSE](LICENSE).
