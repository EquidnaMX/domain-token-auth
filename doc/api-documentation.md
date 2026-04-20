# API Documentation

This package is a **Laravel library**. It does not expose any HTTP endpoints of its own. All API surface is provided as a PHP facade, a middleware, and a service class that host applications consume.

The public PHP API is described below. For HTTP route-level documentation, see [Routes Documentation](routes-documentation.md).

---

## Facade: `Equidna\DomainTokenAuth\Facades\DomainToken`

The `DomainToken` facade (`src/Facades/DomainToken.php`) is the primary entry point. It proxies to `Equidna\DomainTokenAuth\DomainToken` (`src/DomainToken.php`), which delegates to `Equidna\DomainTokenAuth\Services\DomainTokenManager` (`src/Services/DomainTokenManager.php`).

---

### `DomainToken::issue()`

Issues a new domain token and returns an `IssuedToken` DTO.

```php
use Equidna\DomainTokenAuth\Facades\DomainToken;
use Equidna\DomainTokenAuth\DTO\IssuedToken;

$issued = DomainToken::issue(
    domain: 'user',
    owner: $user,
    actions: ['users.export'],
    roles: ['admin'],
    startsAt: now(),
    expiresAt: now()->addDay(),
    name: 'Reporting token',
);

$plainToken = $issued->plainTextToken; // show once — not stored in DB
$tokenModel = $issued->token;          // Equidna\DomainTokenAuth\Models\DomainToken
```

**Parameters:**

| Parameter   | Type                                 | Required | Description                                                       |
| ----------- | ------------------------------------ | -------- | ----------------------------------------------------------------- |
| `domain`    | `string`                             | Yes      | A domain key configured in `config/domain-token-auth.domains`     |
| `owner`     | `Illuminate\Database\Eloquent\Model` | Yes      | Must implement `TokenOwner` and match the domain's `model`        |
| `actions`   | `array<string>`                      | No       | Additional actions to grant directly                              |
| `roles`     | `array<string>`                      | No       | Roles whose mapped actions are merged into the token's action set |
| `startsAt`  | `DateTimeInterface\|null`            | No       | Validity window start; defaults to `now()`                        |
| `expiresAt` | `DateTimeInterface\|null`            | No       | Validity window end; defaults to domain or global TTL             |
| `name`      | `string\|null`                       | No       | Human-readable label for the token                                |

**Returns:** `Equidna\DomainTokenAuth\DTO\IssuedToken` (`src/DTO/IssuedToken.php`)

```php
final class IssuedToken
{
    public readonly string $plainTextToken;
    public readonly DomainToken $token;
}
```

**Throws:**

- `Equidna\DomainTokenAuth\Exceptions\InvalidDomainException` — domain not configured, or owner model does not match the domain's expected model, or owner does not implement `TokenOwner`.

---

### `DomainToken::authenticate()`

Authenticates a plain-text token for a given domain and returns the authenticated context.

```php
use Equidna\DomainTokenAuth\Facades\DomainToken;
use Equidna\DomainTokenAuth\Auth\AuthenticatedDomainToken;

$authenticated = DomainToken::authenticate($plainToken, 'user');

$token = $authenticated->token;   // Equidna\DomainTokenAuth\Models\DomainToken
$domain = $authenticated->domain; // 'user'
$owner = $authenticated->owner;   // Illuminate\Database\Eloquent\Model|null
```

**Parameters:**

| Parameter    | Type     | Required | Description                                 |
| ------------ | -------- | -------- | ------------------------------------------- |
| `plainToken` | `string` | Yes      | The opaque token string issued by `issue()` |
| `domain`     | `string` | Yes      | Domain to authenticate against              |

**Returns:** `Equidna\DomainTokenAuth\Auth\AuthenticatedDomainToken` (`src/Auth/AuthenticatedDomainToken.php`)

```php
final class AuthenticatedDomainToken
{
    public readonly DomainToken $token;
    public readonly string $domain;
    public readonly ?Model $owner;
}
```

**Throws:**

- `Equidna\DomainTokenAuth\Exceptions\TokenValidationException` — token not found, revoked, outside validity window, or tenant isolation violated.
- `Equidna\DomainTokenAuth\Exceptions\InvalidDomainException` — domain not configured.

**Side effects:**

- Updates `last_used_at` on the `DomainToken` model.
- If `equidna/bee-hive` is installed and `bee_hive.apply_tenant_context` is `true`, writes the owner's tenant ID into `TenantContext`.

---

### `DomainToken::revoke()`

Revokes a token by plain-text value. Revocation is irreversible.

```php
$success = DomainToken::revoke($plainToken, 'security-incident');
// Returns false if token not found or already revoked
```

**Parameters:**

| Parameter    | Type     | Required | Description                                                          |
| ------------ | -------- | -------- | -------------------------------------------------------------------- |
| `plainToken` | `string` | Yes      | The opaque token string                                              |
| `reason`     | `string` | No       | Revocation reason stored in `revoked_reason`; defaults to `'manual'` |

**Returns:** `bool` — `true` if the token was found and revoked, `false` otherwise.

---

### `DomainToken::can()`

Checks whether a token grants a specific action.

```php
// Check the token currently stored in the request attributes (after middleware):
if (! DomainToken::can('users.read')) {
    abort(403);
}

// Check an explicit token model:
if (! DomainToken::can('users.write', $tokenModel)) {
    abort(403);
}
```

**Parameters:**

| Parameter | Type                                               | Required | Description                                                                   |
| --------- | -------------------------------------------------- | -------- | ----------------------------------------------------------------------------- |
| `action`  | `string`                                           | Yes      | Action string, e.g. `'users.read'`                                            |
| `token`   | `Equidna\DomainTokenAuth\Models\DomainToken\|null` | No       | Token model to check; if `null`, resolves from the current request attributes |

**Returns:** `bool`

**Action matching rules (via `Equidna\DomainTokenAuth\Support\ActionMatcher`):**

- `*` — grants any action.
- `users.*` — grants any action in the `users` namespace.
- `users.read` — grants the exact action.
- All comparisons are case-insensitive and trimmed.

---

## Middleware: `domain-token`

Registered as `domain-token` by `DomainTokenAuthServiceProvider` (`src/Providers/DomainTokenAuthServiceProvider.php`).

**Class:** `Equidna\DomainTokenAuth\Http\Middleware\ValidateDomainToken` (`src/Http/Middleware/ValidateDomainToken.php`)

**Usage:**

```php
Route::middleware('domain-token:user')->group(function () {
    Route::get('/profile', ProfileController::class);
});
```

**Behaviour:**

1. Reads the `Authorization: Bearer <token>` header.
2. Calls `DomainTokenManager::authenticate($token, $domain)`.
3. On success: stores an `AuthenticatedDomainToken` in `$request->attributes` under the key returned by `DomainTokenManager::requestContextKey()` (`_domain_token_auth_context`).
4. On failure (missing token or `DomainTokenException`): returns HTTP 401 JSON `{"message": "..."}`.

**Accessing the authenticated context in a controller:**

```php
use Equidna\DomainTokenAuth\Auth\AuthenticatedDomainToken;
use Equidna\DomainTokenAuth\Services\DomainTokenManager;

$context = $request->attributes->get(DomainTokenManager::requestContextKey());
// $context is AuthenticatedDomainToken|null
```

---

## Contract: `Equidna\DomainTokenAuth\Contracts\TokenOwner`

`src/Contracts/TokenOwner.php`

Any model that will own tokens must implement this interface:

```php
interface TokenOwner
{
    public function getTokenOwnerIdentifier(): string;
    public function getTokenOwnerDisplayName(): ?string;
}
```

The `HasDomainTokens` trait (`src/Concerns/HasDomainTokens.php`) provides default implementations.

---

## Exception Hierarchy

| Class                                                         | Extends                | Thrown when                                                                     |
| ------------------------------------------------------------- | ---------------------- | ------------------------------------------------------------------------------- |
| `Equidna\DomainTokenAuth\Exceptions\DomainTokenException`     | `RuntimeException`     | Base class                                                                      |
| `Equidna\DomainTokenAuth\Exceptions\InvalidDomainException`   | `DomainTokenException` | Domain not configured, model mismatch, or `TokenOwner` contract not implemented |
| `Equidna\DomainTokenAuth\Exceptions\TokenValidationException` | `DomainTokenException` | Token not found, revoked, expired, or tenant isolation violated                 |
