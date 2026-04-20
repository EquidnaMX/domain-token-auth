# Equidna Domain Token Auth

Paquete Laravel para autenticar requests con tokens opacos seguros, asociados a un dominio funcional, un dueno polimorfico, roles, acciones, vigencia y revocacion.

## Caracteristicas

- Tokens opacos: el valor plano solo se muestra una vez.
- Persistencia segura: solo se guarda el hash SHA-256 del token.
- Tokens asociados siempre a un `domain` y a un modelo dueno.
- Dueno polimorfico mediante `tokenable_type` y `tokenable_id`.
- Middleware por dominio: `domain-token:user`, `domain-token:app`, etc.
- Roles configurables que se expanden a acciones.
- Acciones directas con soporte para comodines como `*` y `users.*`.
- Ventana de validez con `starts_at` y `expires_at`.
- Revocacion irreversible mediante `revoked_at`.

## Instalacion

```bash
composer require equidna/domain-token-auth
php artisan vendor:publish --tag=domain-token-auth-config
php artisan vendor:publish --tag=domain-token-auth-migrations
php artisan migrate
```

El archivo de configuracion publicado queda en:

```text
config/domain-token-auth.php
```

## Configuracion

El archivo `config/domain-token-auth.php` define dos cosas principales:

- La configuracion global de tokens.
- Los dominios funcionales donde un token puede ser emitido y autenticado.

Ejemplo:

```php
return [
    'token' => [
        'table' => 'domain_tokens',
        'prefix' => 'dtk_',
        'length' => 64,
        'default_ttl_minutes' => 60,
    ],

    'domains' => [
        'user' => [
            'model' => App\Models\User::class,
            'default_actions' => ['users.read'],
            'roles' => [
                'viewer' => ['users.read'],
                'admin' => ['users.*'],
            ],
            'default_ttl_minutes' => 60,
        ],

        'app' => [
            'model' => App\Models\Application::class,
            'default_actions' => ['apps.read'],
            'roles' => [
                'integrator' => ['orders.read', 'orders.write'],
                'owner' => ['apps.*', 'orders.*'],
            ],
            'default_ttl_minutes' => 1440,
        ],
    ],
];
```

### Opciones globales de token

`token.table` define la tabla donde se guardan los tokens. Por defecto es `domain_tokens`.

`token.prefix` define el prefijo del token plano. Por defecto es `dtk_`.

`token.length` define la longitud aleatoria despues del prefijo.

`token.default_ttl_minutes` define la expiracion por defecto cuando el dominio no define una propia. Si el valor es `0`, los tokens no expiran automaticamente salvo que se pase `expiresAt` al emitirlos.

### Dominios

Un `domain` no representa un dominio web como `example.com`. Representa un ambito funcional donde el token es valido.

Buenos ejemplos de dominios:

```php
'user'
'app'
'service'
'distributor'
'tenant'
'partner'
```

Evita nombres que describen canales o implementacion:

```php
'api-v1'
'mobile'
'frontend'
'admin-controller'
```

Cada dominio debe declarar:

`model`: clase Eloquent que puede ser duena de tokens para ese dominio. El modelo debe implementar `TokenOwner`.

`default_actions`: acciones concedidas a todo token emitido en ese dominio.

`roles`: mapa de roles a acciones. Al emitir un token con roles, el paquete expande esos roles a acciones concretas.

`default_ttl_minutes`: TTL por defecto de los tokens de ese dominio. Tiene prioridad sobre `token.default_ttl_minutes`.

## Modelo dueno

Todo token debe tener dominio y dueno. El modelo dueno debe implementar `TokenOwner` y usar el trait `HasDomainTokens`.

```php
namespace App\Models;

use Equidna\DomainTokenAuth\Concerns\HasDomainTokens;
use Equidna\DomainTokenAuth\Contracts\TokenOwner;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements TokenOwner
{
    use HasDomainTokens;
}
```

El paquete valida que el modelo usado al emitir el token coincida con el `model` configurado para el dominio. Por ejemplo, un token del dominio `user` solo puede pertenecer a `App\Models\User` si ese es el modelo configurado.

### Integracion con BeeHive

Si el modelo dueno del token usa el trait `Equidna\BeeHive\Traits\BelongsToTenant`, la autenticacion del token establece automaticamente el tenant en `Equidna\BeeHive\Tenancy\TenantContext`.

Esto permite que las consultas y acciones posteriores del request usen el mismo contexto de tenant que el dueno del token:

```php
use Equidna\BeeHive\Traits\BelongsToTenant;
use Equidna\DomainTokenAuth\Concerns\HasDomainTokens;
use Equidna\DomainTokenAuth\Contracts\TokenOwner;
use Illuminate\Database\Eloquent\Model;

class Application extends Model implements TokenOwner
{
    use BelongsToTenant;
    use HasDomainTokens;
}
```

Flujo:

- Se autentica el bearer token con `domain-token:app`.
- El paquete resuelve el modelo dueno del token.
- Si el modelo usa `BelongsToTenant`, lee su tenant key.
- El valor se escribe en `TenantContext`.
- Las acciones posteriores del request quedan bajo ese tenant.

## Como se usa `domain` en un entorno real

El `domain` separa contextos de autenticacion y evita que un token valido en un ambito sea aceptado en otro.

Por ejemplo, una app SaaS podria tener:

- `user`: tokens para usuarios humanos o procesos internos asociados a un usuario.
- `app`: tokens para aplicaciones integradoras o clientes API.
- `service`: tokens machine-to-machine para servicios internos.
- `distributor`: tokens para distribuidores o partners.

Cuando emites un token, el dominio decide que tipo de dueno puede tener y que roles/acciones existen:

```php
use Equidna\DomainTokenAuth\Facades\DomainToken;

$issued = DomainToken::issue(
    domain: 'app',
    owner: $application,
    roles: ['integrator'],
    name: 'Shopify connector'
);

$plainToken = $issued->plainTextToken;
```

Ese token queda asociado al dominio `app` y al modelo dueno:

```text
domain: app
tokenable_type: App\Models\Application
tokenable_id: 15
roles: ["integrator"]
actions: ["apps.read", "orders.read", "orders.write"]
```

Si ese token se usa en una ruta protegida con `domain-token:app`, puede autenticarse:

```php
Route::middleware('domain-token:app')->group(function () {
    Route::post('/integrations/orders', StoreExternalOrderController::class);
});
```

Si el mismo token se usa en una ruta protegida con `domain-token:user`, sera rechazado aunque exista, no este vencido y no este revocado:

```php
Route::middleware('domain-token:user')->get('/profile', ProfileController::class);
```

## Emitir tokens

```php
use Equidna\DomainTokenAuth\Facades\DomainToken;

$issued = DomainToken::issue(
    domain: 'user',
    owner: $user,
    roles: ['viewer'],
    actions: ['users.export'],
    name: 'Reporting token'
);

$plainToken = $issued->plainTextToken;
```

El token plano debe mostrarse o guardarse en ese momento. Despues no se puede recuperar porque la base de datos solo guarda el hash.

Tambien puedes definir vigencia manual:

```php
$issued = DomainToken::issue(
    domain: 'user',
    owner: $user,
    startsAt: now(),
    expiresAt: now()->addDay()
);
```

## Middleware

El middleware valida tres cosas:

- El request trae un bearer token.
- El token existe para el dominio indicado.
- El token no esta revocado y esta dentro de su ventana de validez.

```php
Route::middleware('domain-token:user')->group(function () {
    Route::get('/profile', ProfileController::class);
});
```

El middleware solo autentica el dominio. Las acciones se verifican dentro del codigo de aplicacion.

## Verificar acciones

```php
use Equidna\DomainTokenAuth\Facades\DomainToken;

if (! DomainToken::can('users.read')) {
    abort(403);
}
```

Tambien puedes verificar contra un modelo de token explicito:

```php
if (! DomainToken::can('users.write', $token)) {
    abort(403);
}
```

Los comodines permiten conceder grupos de acciones:

```php
'admin' => ['users.*']
```

Ese rol permite acciones como:

```text
users.read
users.write
users.delete
```

## Comando Artisan

Puedes generar tokens desde consola:

```bash
php artisan domain-token:generate user 1 --roles=admin,viewer --actions=users.export --name="CLI token"
```

Argumentos:

- `domain`: dominio configurado en `config/domain-token-auth.php`.
- `owner_id`: ID del modelo dueno dentro del modelo configurado para ese dominio.

Opciones:

- `--roles`: roles separados por coma.
- `--actions`: acciones adicionales separadas por coma.
- `--name`: etiqueta opcional para identificar el token.
- `--starts-at`: fecha de inicio.
- `--expires-at`: fecha de expiracion.

Ejemplo con vigencia:

```bash
php artisan domain-token:generate app 15 --roles=integrator --expires-at="2026-05-01 00:00:00"
```

## Revocar tokens

Desde codigo:

```php
DomainToken::revoke($plainToken, 'security-incident');
```

La revocacion es irreversible por diseno. Un token revocado deja de autenticarse inmediatamente.

## Pruebas

```bash
composer test
```
