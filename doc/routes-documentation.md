# Routes Documentation

This package is a **Laravel library**. It does **not** register any HTTP routes of its own.

---

## How Route Protection Works

The package provides a named middleware alias `domain-token` that host applications attach to their own routes. The alias is registered in `DomainTokenAuthServiceProvider::boot()` (`src/Providers/DomainTokenAuthServiceProvider.php`):

```php
$router->aliasMiddleware('domain-token', ValidateDomainToken::class);
```

The middleware class is `Equidna\DomainTokenAuth\Http\Middleware\ValidateDomainToken` (`src/Http/Middleware/ValidateDomainToken.php`).

---

## Applying the Middleware in the Host Application

Add the `domain-token:{domain}` middleware to any route or route group in the host application. The `{domain}` segment must match a key configured under `domains` in `config/domain-token-auth.php`.

### Single route

```php
Route::get('/profile', ProfileController::class)
    ->middleware('domain-token:user');
```

### Route group

```php
Route::middleware('domain-token:app')->group(function () {
    Route::post('/integrations/orders', StoreExternalOrderController::class);
    Route::get('/integrations/status', IntegrationStatusController::class);
});
```

### Multiple domains on different groups

```php
// User-domain routes
Route::middleware('domain-token:user')->prefix('/user')->group(function () {
    Route::get('/me', UserProfileController::class);
});

// App-domain routes
Route::middleware('domain-token:app')->prefix('/app')->group(function () {
    Route::post('/webhooks', AppWebhookController::class);
});
```

---

## Authentication Flow per Request

```
Incoming HTTP request
  └─ Authorization: Bearer dtk_<random>
        │
        ▼
  domain-token:{domain} middleware
        │
        ├─ No bearer token → 401 {"message":"Token not found."}
        │
        ├─ Token not found in DB → 401 {"message":"Token not found."}
        ├─ Token revoked → 401 {"message":"Token revoked."}
        ├─ Token outside validity window → 401 {"message":"Token out of validity window."}
        ├─ Tenant isolation violated → 401 {"message":"Token tenant mismatch."}
        │
        └─ Valid → stores AuthenticatedDomainToken in request attributes
                    → calls $next($request)
```

---

## Configuration Flags

The domain name passed to the middleware must match a key in the `domains` array of `config/domain-token-auth.php`. There are no route-prefix or route-registration configuration options; this package does not modify the host application's route list.

---

## Notes

- Domain isolation is enforced at the middleware level: a token issued for domain `app` will be rejected by middleware `domain-token:user` even if the token is otherwise valid.
- Action-level authorization (e.g. `DomainToken::can('users.write')`) is separate and must be checked within the controller or application logic after the middleware passes.
