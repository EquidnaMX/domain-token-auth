# Artisan Commands

This package registers one custom Artisan command.

---

## `domain-token:generate`

**Class:** `Equidna\DomainTokenAuth\Console\Commands\GenerateDomainToken` (`src/Console/Commands/GenerateDomainToken.php`)

**Purpose:** Generate a secure domain token from the command line. Useful for bootstrapping integrations, creating long-lived machine tokens, or testing without writing ad-hoc scripts.

The command resolves the owner model from the database, validates it against the configured domain, and calls `DomainToken::issue()`. The plain-text token is printed to the console exactly once and is not stored anywhere.

---

### Signature

```
php artisan domain-token:generate
    {domain : Configured domain key}
    {owner_id : Primary key of the owner model}
    [--roles=]
    [--actions=]
    [--name=]
    [--starts-at=]
    [--expires-at=]
```

---

### Arguments

| Argument   | Required | Description                                                                         |
| ---------- | -------- | ----------------------------------------------------------------------------------- |
| `domain`   | Yes      | A domain key that exists in `config/domain-token-auth.domains` (e.g. `user`, `app`) |
| `owner_id` | Yes      | The primary key value of the owner record in the model configured for that domain   |

---

### Options

| Option         | Required | Format                   | Description                                                                                                                          |
| -------------- | -------- | ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------ |
| `--roles`      | No       | CSV string               | Comma-separated list of roles. Each role must be defined under the domain's `roles` map. Example: `admin,viewer`                     |
| `--actions`    | No       | CSV string               | Comma-separated list of additional actions to grant directly, beyond those resolved from roles. Example: `users.export,users.report` |
| `--name`       | No       | String                   | Optional human-readable label to identify the token (stored in `name` column)                                                        |
| `--starts-at`  | No       | `Y-m-d H:i:s` or ISO8601 | Start of the validity window. Defaults to the current time if omitted                                                                |
| `--expires-at` | No       | `Y-m-d H:i:s` or ISO8601 | End of the validity window. Defaults to the domain or global TTL if omitted                                                          |

---

### Example Usage

**Minimal — grant default domain actions:**

```bash
php artisan domain-token:generate user 1
```

**With roles:**

```bash
php artisan domain-token:generate user 1 --roles=admin
```

**With roles and a name:**

```bash
php artisan domain-token:generate user 42 --roles=viewer --name="Reporting service"
```

**With explicit actions and expiration:**

```bash
php artisan domain-token:generate app 15 --roles=integrator --actions=apps.webhook --expires-at="2026-12-31 23:59:59"
```

**With a start date (token not valid until then):**

```bash
php artisan domain-token:generate user 7 --starts-at="2026-05-01 00:00:00" --expires-at="2026-05-31 23:59:59"
```

---

### Output

On success, the command prints:

```
Token generated successfully. Copy this value now, it will not be shown again:
dtk_<64 random characters>
```

On failure (invalid domain, model not found, model does not implement `TokenOwner`, invalid date), the command prints an error message and exits with `FAILURE` (exit code 1).

---

### Scheduling

This command is not scheduled by the package. It is intended to be run manually or as part of a deployment or bootstrap script. If the host application needs to generate tokens programmatically on a schedule, use the `DomainToken` facade directly in a scheduled job or command.

---

## Default Laravel Commands

All standard Laravel Artisan commands remain available in the host application. The package does not modify, remove, or override any default commands.
