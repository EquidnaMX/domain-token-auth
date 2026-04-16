# Handover — equidna/domain-token-auth

**Fecha:** 2026-04-10  
**Repo:** EquidnaMX/ATL (branch: main)  
**Stack:** PHP 8.2+, Laravel 11 / 12, SQLite (testing)

---

## Qué es el paquete

`equidna/domain-token-auth` es un paquete Laravel de autenticación por **token opaco seguro** con dominio, vigencia, revocación y autorización por acciones/roles configurables. Punto de partida arquitectónico: `EquidnaMX/laravel-keygen`.

---

## Estado actual — COMPLETADO ✅

Todos los archivos de código están creados y la suite de pruebas pasa en verde:  
**11 tests, 11 assertions — OK** (PHP 8.4.16 / PHPUnit 11.5.55)

---

## Estructura del paquete

```
composer.json                             # paquete equidna/domain-token-auth, Laravel 11/12
phpunit.xml
config/
  domain-token-auth.php                  # dominios, modelos, roles, TTL, prefijo de token
src/
  DomainToken.php                        # Entrypoint público (inyectable o facade)
  Auth/
    AuthenticatedDomainToken.php         # DTO del contexto autenticado en request
  Concerns/
    HasDomainTokens.php                  # Trait para modelos tokenizables (MorphMany + contrato)
  Console/
    Commands/
      GenerateDomainToken.php            # php artisan domain-token:generate
  Contracts/
    TokenOwner.php                       # Interfaz que debe implementar cada modelo dueño
  database/
    migrations/
      2026_04_06_000000_create_domain_tokens_table.php
  DTO/
    IssuedToken.php                      # plainTextToken + DomainToken model
  Exceptions/
    DomainTokenException.php
    InvalidDomainException.php
    TokenValidationException.php
  Facades/
    DomainToken.php                      # Facade → DomainToken::class
  Http/
    Middleware/
      ValidateDomainToken.php            # alias: domain-token (requiere param dominio)
  Models/
    DomainToken.php                      # Eloquent, fillable, casts, morphTo, isRevoked, isWithinWindow
  Providers/
    DomainTokenAuthServiceProvider.php   # register + boot, auto-discovery
  Services/
    DomainTokenManager.php               # Núcleo: issue, authenticate, revoke, can
  Support/
    ActionMatcher.php                    # parseCsv, allows (wildcards * y domain.*)
tests/
  FakeApplication.php
  FakeUser.php
  TestCase.php                           # Testbench + SQLite in-memory + routes de prueba
  Feature/
    DomainTokenFlowTest.php              # Flujos de integración middleware + revocación + vigencia + roles
  Unit/
    ActionMatcherTest.php                # Matching exacto, wildcards, parseCsv
```

---

## Tabla BD — `domain_tokens`

| Columna                       | Tipo            | Notas                                   |
| ----------------------------- | --------------- | --------------------------------------- |
| id                            | bigint PK       |                                         |
| token_hash                    | char(64) UNIQUE | SHA-256 del token plano                 |
| domain                        | varchar(64)     | dominio configurado                     |
| name                          | varchar         | nullable, etiqueta                      |
| roles                         | JSON            | nullable, roles asignados               |
| actions                       | JSON            | nullable, acciones expandidas           |
| tokenable_type / tokenable_id | morphs          | dueño polimórfico obligatorio           |
| starts_at                     | timestamp       | nullable                                |
| expires_at                    | timestamp       | nullable                                |
| last_used_at                  | timestamp       | nullable, actualizado en authenticate() |
| revoked_at                    | timestamp       | nullable — presencia = revocado         |
| revoked_reason                | varchar         | nullable                                |
| created_at / updated_at       | timestamps      |                                         |

Índices: `(domain, revoked_at)`, `(domain, starts_at, expires_at)` y `(domain, tokenable_type, tokenable_id)`

---

## Decisiones técnicas clave

| Decisión              | Valor                                                                               |
| --------------------- | ----------------------------------------------------------------------------------- |
| Formato de token      | `dtk_` + 64 chars aleatorios (`Str::random`)                                        |
| Persistencia          | Solo hash SHA-256 — el plain token se devuelve UNA sola vez                         |
| Laravel               | 11.x y 12.x                                                                         |
| Vigencia              | `starts_at` y `expires_at` opcionales                                               |
| Revocación            | Soft via `revoked_at` — irreversible por diseño                                     |
| Dominio en middleware | Parámetro explícito: `domain-token:user`                                            |
| Permisos              | En código de aplicación — middleware solo autentica dominio                         |
| Acciones              | String CSV normalizado, comodines `*` y `domain.*`                                  |
| Roles                 | Definidos por dominio en config, se expanden a acciones en emisión                  |
| Modelo base           | Requiere `TokenOwner` interface + `HasDomainTokens` trait (o implementación propia) |

---

## Uso en la app consumidora

### 1. Instalar

```bash
composer require equidna/domain-token-auth
php artisan vendor:publish --tag=domain-token-auth-config
php artisan vendor:publish --tag=domain-token-auth-migrations
php artisan migrate
```

### 2. Implementar el modelo

```php
use Equidna\DomainTokenAuth\Concerns\HasDomainTokens;
use Equidna\DomainTokenAuth\Contracts\TokenOwner;

class User extends Model implements TokenOwner
{
    use HasDomainTokens;
}
```

### 3. Configurar dominio en `config/domain-token-auth.php`

```php
'domains' => [
    'user' => [
        'model' => App\Models\User::class,
        'default_actions' => ['users.read'],
        'roles' => [
            'admin' => ['users.*'],
            'viewer' => ['users.read'],
        ],
        'default_ttl_minutes' => 60,
    ],
],
```

### 4. Emitir token

```php
$issued = DomainToken::issue(
    domain: 'user',
    owner: $user,
    roles: ['admin'],       // expande a acciones via config
    actions: ['audit.log'], // acciones adicionales directas
    name: 'Admin CLI token',
);

$plainToken = $issued->plainTextToken; // guardar y mostrar UNA sola vez
```

Artisan:

```bash
php artisan domain-token:generate user 42 --roles=admin --actions=audit.log --name="CLI"
```

### 5. Middleware

```php
Route::middleware('domain-token:user')->group(function () {
    Route::get('/profile', ProfileController::class);
});
```

### 6. Verificar acciones en código

```php
// En controlador (el facade lee el token del request context):
if (! DomainToken::can('users.write')) {
    abort(403);
}

// O pasando el token explícitamente:
DomainToken::can('users.write', $tokenModel);
```

### 7. Revocar

```php
DomainToken::revoke($plainToken, 'security-incident');
```

---

## Qué falta / posibles siguientes pasos

1. **Comando `domain-token:revoke`** — revocar por ID de registro sin exponer token plano.
2. **Comando `domain-token:list`** — listar tokens activos/revocados por dominio/modelo.
3. **Auditoría de uso** — guardar IP, user-agent, ruta en cada autenticación (tabla separada).
4. **Publicar en Packagist** — ajustar `composer.json` (homepage, keywords, soporte de versión).
5. **GitHub Actions CI** — matrix PHP 8.2/8.3/8.4 × Laravel 11/12.
6. **Rotación de tokens** — método `rotate()` que revoca el actual y emite uno nuevo.
7. **Política de expiración configurable por dominio** sin override manual por token.

---

## Cómo correr pruebas

```bash
cd e:\Desarrollo\Equidna\ATL
composer install   # solo si no existe vendor/
composer test
# Esperado: 11 tests, 11 assertions — OK
```
