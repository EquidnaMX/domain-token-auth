# Equidna Domain Token Auth

Secure domain-based token authentication package for Laravel 11 and 12.

## Features

- Opaque tokens with SHA-256 hash persistence
- Domain-aware token validation (`user`, `app`, etc.)
- Role and action-based authorization with wildcards (`*`, `users.*`)
- Token validity window (`starts_at` / `expires_at`)
- Revocation support
- Middleware for domain validation
- Config-driven domain to model mapping

## Installation

```bash
composer require equidna/domain-token-auth
php artisan vendor:publish --tag=domain-token-auth-config
php artisan vendor:publish --tag=domain-token-auth-migrations
php artisan migrate
```

## Basic Usage

```php
$issued = \Equidna\DomainTokenAuth\Facades\DomainToken::issue(
    domain: 'user',
    owner: $user,
    roles: ['viewer'],
    actions: ['users.read', 'users.write']
);

$plainToken = $issued->plainTextToken;
```

```php
Route::middleware(['domain-token:user'])->group(function () {
    Route::get('/profile', function () {
        // Domain token is valid for "user" domain.
    });
});
```

Action checks are done in code:

```php
if (!\Equidna\DomainTokenAuth\Facades\DomainToken::can('users.read')) {
    abort(403);
}

## Artisan Command

```bash
php artisan domain-token:generate user 1 --roles=admin,viewer --actions=users.export
```
```
