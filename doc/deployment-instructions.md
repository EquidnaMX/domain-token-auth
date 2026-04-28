# Deployment Instructions

This package is a **Laravel library**, not a standalone application. Deployment refers to integrating it into a host Laravel application.

---

## System Requirements

| Requirement    | Version                                                                  |
| -------------- | ------------------------------------------------------------------------ |
| PHP            | `^8.2`                                                                   |
| Laravel        | `^12.0`                                                                  |
| PHP extensions | `pdo`, `mbstring`, `json` (standard Laravel requirements)                |
| Database       | Any database supported by Laravel's Eloquent (MySQL, PostgreSQL, SQLite) |
| Optional       | `equidna/bee-hive ^1.0` for multi-tenant context propagation             |

No dedicated cache store, queue driver, or external services are required by the package itself.

---

## Package Installation

### 1. Add to the host application

```bash
composer require equidna/domain-token-auth
```

Laravel's package auto-discovery mechanism registers `Equidna\DomainTokenAuth\Providers\DomainTokenAuthServiceProvider` automatically via the `extra.laravel` entry in `composer.json`. No manual registration is needed.

The facade alias `DomainToken` → `Equidna\DomainTokenAuth\Facades\DomainToken` is also registered automatically.

### 2. Publish the configuration

```bash
php artisan vendor:publish --tag=domain-token-auth:config
```

This copies `config/domain-token-auth.php` into the host application's `config/` directory.

Both tags `domain-token-auth` and `domain-token-auth:config` publish the configuration file.

### 3. Publish and run migrations

Option A — publish migrations, then migrate (recommended for production):

```bash
php artisan vendor:publish --tag=domain-token-auth:migrations
php artisan migrate
```

Option B — let the package auto-load migrations (suitable for development):

The service provider calls `$this->loadMigrationsFrom(...)` automatically, so `php artisan migrate` will pick up the package's migration without publishing it first.

Both tags `domain-token-auth` and `domain-token-auth:migrations` publish the migration file.

---

## Environment Configuration

No dedicated `.env` variables are required by the package for basic operation. The following optional variables control the BeeHive multi-tenant integration:

| Variable                                | Default | Description                                                                               |
| --------------------------------------- | ------- | ----------------------------------------------------------------------------------------- |
| `DOMAIN_TOKEN_APPLY_TENANT_CONTEXT`     | `true`  | Whether to write the owner's tenant ID into `TenantContext` after authentication          |
| `DOMAIN_TOKEN_ENFORCE_TENANT_ISOLATION` | `true`  | Whether to reject authentication when active `TenantContext` differs from owner tenant ID |

These variables are only meaningful when `equidna/bee-hive` is installed and the owner model uses `BelongsToTenant`.

---

## Configuration File

After publishing, edit `config/domain-token-auth.php` to define:

### Global token settings

```php
'token' => [
    'table'                => 'domain_tokens', // table name
    'prefix'               => 'dtk_',          // plain-token prefix
    'length'               => 64,               // random suffix length
    'default_ttl_minutes'  => 60,              // 0 = no expiry unless set per-token
],
```

### Domain definitions

Each key under `domains` is a functional domain name (e.g. `'user'`, `'app'`):

```php
'domains' => [
    'user' => [
        'model'               => App\Models\User::class,  // must implement TokenOwner
        'default_actions'     => ['users.read'],
        'roles'               => [
            'viewer' => ['users.read'],
            'admin'  => ['users.*'],
        ],
        'default_ttl_minutes' => 60,
    ],
],
```

---

## Owner Model Setup

Every model that will own tokens must implement `Equidna\DomainTokenAuth\Contracts\TokenOwner` (`src/Contracts/TokenOwner.php`) and use the `Equidna\DomainTokenAuth\Concerns\HasDomainTokens` trait (`src/Concerns/HasDomainTokens.php`):

```php
use Equidna\DomainTokenAuth\Concerns\HasDomainTokens;
use Equidna\DomainTokenAuth\Contracts\TokenOwner;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements TokenOwner
{
    use HasDomainTokens;
}
```

`HasDomainTokens` provides the `domainTokens()` `MorphMany` relationship and default implementations of `getTokenOwnerIdentifier()` and `getTokenOwnerDisplayName()`.

---

## Database Migrations

The package now includes two migrations:

- `src/database/migrations/2026_04_06_000000_create_domain_tokens_table.php`
- `src/database/migrations/2026_04_22_000001_add_data_to_domain_tokens_table.php`
- `src/database/migrations/2026_04_28_000002_change_tokenable_id_to_varchar_64_on_domain_tokens_table.php`

Together they create and evolve the `domain_tokens` table with the following key columns:

| Column           | Type        | Notes                                                 |
| ---------------- | ----------- | ----------------------------------------------------- |
| `id`             | bigint      | Primary key                                           |
| `token_hash`     | char(64)    | SHA-256 hash, unique                                  |
| `domain`         | varchar(64) | Functional domain name                                |
| `name`           | varchar     | Optional human label                                  |
| `roles`          | JSON        | Roles granted to this token                           |
| `actions`        | JSON        | Resolved actions                                      |
| `data`           | JSON        | Optional custom payload available post-authentication |
| `tokenable_type` | varchar     | Polymorphic owner type                                |
| `tokenable_id`   | varchar(64) | Polymorphic owner ID                                  |
| `starts_at`      | timestamp   | Validity window start                                 |
| `expires_at`     | timestamp   | Validity window end                                   |
| `last_used_at`   | timestamp   | Updated on each successful authentication             |
| `revoked_at`     | timestamp   | Set on revocation; irreversible                       |
| `revoked_reason` | varchar     | Optional revocation reason                            |

Migrations are idempotent: table and column existence are checked before modifications.

---

## Deployment Workflow (Host Application)

```
1. composer install --no-dev --optimize-autoloader
2. php artisan vendor:publish --tag=domain-token-auth:config   (first deploy only)
3. php artisan vendor:publish --tag=domain-token-auth:migrations (first deploy only)
4. php artisan migrate --force
5. php artisan config:cache
6. php artisan route:cache
```

No queue workers or scheduler entries are required by this package.

---

## Notes for Local Development

The test suite uses an in-memory SQLite database via Orchestra Testbench. No additional database setup is needed to run tests:

```bash
composer test
# or
vendor/bin/phpunit
```
