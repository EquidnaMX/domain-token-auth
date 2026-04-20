# Monitoring

This package is a **Laravel library**. It does not configure logging channels, monitoring tools, or alerting. All observability is inherited from and configured by the host application.

---

## Logging

The package itself does not write log entries. The host application's `config/logging.php` governs all log behavior.

**What to log in the host application:**

- Failed authentication attempts (the middleware catches `DomainTokenException` and returns a 401; consider adding a log call in the host's exception handler or a custom middleware wrapper for high-security environments).
- Token issuance events (log the `IssuedToken->token->id`, `domain`, `owner`, and `name` — never the plain-text token).
- Token revocations (log the `revoked_reason` and the revoked token's metadata).

**Example — custom logging wrapper around the middleware in the host app:**

```php
use Equidna\DomainTokenAuth\Exceptions\DomainTokenException;
use Illuminate\Support\Facades\Log;

// In a custom host-application middleware:
try {
    $authenticated = $this->tokenManager->authenticate($bearerToken, $domain);
} catch (DomainTokenException $e) {
    Log::warning('domain-token-auth: authentication failed', [
        'domain'  => $domain,
        'reason'  => $e->getMessage(),
        'ip'      => $request->ip(),
    ]);
    return response()->json(['message' => $e->getMessage()], 401);
}
```

---

## Recommended Metrics

These metrics should be collected by the host application's APM or monitoring stack:

| Metric                                                   | Why it matters                                                                               |
| -------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Rate of 401 responses on `domain-token`-protected routes | Spike may indicate credential stuffing or a misconfigured integration                        |
| `domain_tokens` row count per domain                     | Growth rate shows token issuance volume; unexpected growth may indicate abuse                |
| `revoked_at IS NOT NULL` count                           | Tracks revocation activity; sudden increases warrant investigation                           |
| `last_used_at` staleness per domain                      | Tokens not used for a long time may be candidates for cleanup                                |
| DB query latency on `domain_tokens`                      | The table is indexed by `token_hash`, `domain`, and `tenant_id`; watch for index degradation |

---

## Recommended Alerts

| Condition                          | Suggested threshold                    | Severity |
| ---------------------------------- | -------------------------------------- | -------- |
| 401 rate on protected routes       | > 10% of requests in a 5-minute window | Warning  |
| 401 rate on protected routes       | > 50% of requests in a 5-minute window | Critical |
| DB query latency for token lookups | p95 > 50 ms                            | Warning  |
| Unexpected spike in token issuance | 3× normal rate                         | Warning  |

---

## Monitoring Tools

No monitoring tools are bundled with or required by the package. The following tools integrate naturally with the host Laravel application:

| Tool                        | Purpose                                                                    |
| --------------------------- | -------------------------------------------------------------------------- |
| **Laravel Telescope**       | Inspect individual requests, DB queries, and exceptions during development |
| **Laravel Horizon**         | Not applicable — the package uses no queues                                |
| **Sentry / Bugsnag**        | Capture and alert on unhandled exceptions in the host application          |
| **New Relic / Datadog APM** | Distributed tracing and DB query profiling                                 |

---

## Troubleshooting

### Symptom: All token authentications return 401

**Check:**

1. The `Authorization: Bearer <token>` header is present and correctly formatted.
2. The `domain` passed to the middleware matches a key in `config/domain-token-auth.domains`.
3. The `domain_tokens` table exists and the migration has been run (`php artisan migrate`).
4. The token has not been revoked (`revoked_at IS NULL`).
5. The token's validity window includes the current timestamp (`starts_at <= now() <= expires_at`).

### Symptom: Tenant isolation errors in BeeHive-enabled applications

**Check:**

1. `DOMAIN_TOKEN_ENFORCE_TENANT_ISOLATION` is set correctly in `.env`.
2. The active `TenantContext` tenant ID matches the `tenant_id` stored on the token.
3. If using legacy tokens (issued before `tenant_id` was stored), set `DOMAIN_TOKEN_ALLOW_LEGACY_TOKENS=true`.

### Symptom: `InvalidDomainException` at token issuance

**Check:**

1. The domain key exists in `config/domain-token-auth.domains`.
2. The `model` value for that domain is a fully-qualified class name that exists.
3. The owner model passed to `issue()` is an instance of the configured model class.
4. The owner model implements `Equidna\DomainTokenAuth\Contracts\TokenOwner`.

### Symptom: Slow queries on large `domain_tokens` tables

The table ships with composite indexes on `(domain, revoked_at)`, `(domain, starts_at, expires_at)`, `(domain, tokenable_type, tokenable_id)`, and `(tenant_id, domain)`. Verify these indexes exist and have not been dropped:

```sql
SHOW INDEX FROM domain_tokens;
```
