# Architecture Diagrams

All diagrams use [Mermaid](https://mermaid.js.org/).

---

## System Context Diagram

Shows the package in relation to the host Laravel application and external systems.

```mermaid
C4Context
    title System Context — equidna/domain-token-auth

    Person(developer, "Developer / Operator", "Generates tokens via Artisan or code")
    Person(client, "API Client", "External service or user consuming a protected API")

    System_Boundary(host, "Host Laravel Application") {
        System(hostApp, "Laravel Application", "Hosts and configures the package; owns the business routes")
        System(pkg, "equidna/domain-token-auth", "Issues, authenticates, and revokes domain-scoped tokens")
    }

    System_Ext(db, "Database", "Stores domain_tokens table (MySQL / PostgreSQL / SQLite)")
    System_Ext(beehive, "equidna/bee-hive", "Optional — provides TenantContext for multi-tenant isolation")

    Rel(developer, hostApp, "php artisan domain-token:generate")
    Rel(client, hostApp, "HTTP requests with Bearer token")
    Rel(hostApp, pkg, "Registers and uses package")
    Rel(pkg, db, "Reads/writes domain_tokens")
    Rel(pkg, beehive, "Reads/writes TenantContext (optional)")
```

---

## Container Diagram

Shows the runtime containers involved when the package is active in a host application.

```mermaid
flowchart TD
    subgraph Client["API Client"]
        HTTP["HTTP Request\nAuthorization: Bearer dtk_..."]
    end

    subgraph HostApp["Host Laravel Application"]
        Router["Laravel Router"]
        Middleware["domain-token:{domain} Middleware\nValidateDomainToken"]
        Controller["Host Controller"]
        Facade["DomainToken Facade"]
        Manager["DomainTokenManager\n(Service)"]
        Model["DomainToken Model\n(Eloquent)"]
        ActionMatcher["ActionMatcher\n(Support)"]
    end

    subgraph Storage["Storage"]
        DB[(domain_tokens\ntable)]
    end

    subgraph Optional["Optional — equidna/bee-hive"]
        TenantCtx["TenantContext"]
    end

    HTTP --> Router
    Router --> Middleware
    Middleware --> Manager
    Manager --> Model
    Model --> DB
    Manager --> ActionMatcher
    Manager --> TenantCtx
    Middleware --> Controller
    Controller --> Facade
    Facade --> Manager
```

---

## Component Diagram

Shows all classes within the package and their relationships.

```mermaid
classDiagram
    direction LR

    class DomainToken {
        +issue(domain, owner, actions, roles, startsAt, expiresAt, name) IssuedToken
        +authenticate(plainToken, domain) AuthenticatedDomainToken
        +revoke(plainToken, reason) bool
        +can(action, token) bool
    }

    class DomainTokenFacade {
        <<Facade>>
        getFacadeAccessor() string
    }

    class DomainTokenAuthServiceProvider {
        +register() void
        +boot(router) void
    }

    class DomainTokenManager {
        +issue(...) IssuedToken
        +authenticate(plainToken, domain) AuthenticatedDomainToken
        +revoke(plainToken, reason) bool
        +can(token, action) bool
        +requestContextKey() string
    }

    class ValidateDomainToken {
        <<Middleware>>
        +handle(request, next, domain) Response
    }

    class DomainTokenModel {
        <<Model>>
        +tokenable() MorphTo
        +isRevoked() bool
        +isWithinWindow(at) bool
    }

    class IssuedToken {
        <<DTO>>
        +plainTextToken string
        +token DomainToken
    }

    class AuthenticatedDomainToken {
        <<DTO>>
        +token DomainToken
        +domain string
        +owner Model|null
    }

    class TokenOwner {
        <<interface>>
        +getTokenOwnerIdentifier() string
        +getTokenOwnerDisplayName() string|null
    }

    class HasDomainTokens {
        <<trait>>
        +domainTokens() MorphMany
        +getTokenOwnerIdentifier() string
        +getTokenOwnerDisplayName() string|null
    }

    class ActionMatcher {
        <<Support>>
        +allows(grantedActions, requiredAction) bool
        +parseCsv(csv) array
        +normalize(action) string
    }

    class GenerateDomainToken {
        <<Command>>
        +handle(domainToken) int
    }

    DomainTokenFacade --> DomainToken : proxies
    DomainTokenAuthServiceProvider --> DomainToken : registers singleton
    DomainTokenAuthServiceProvider --> DomainTokenManager : registers singleton
    DomainTokenAuthServiceProvider --> ValidateDomainToken : aliases middleware
    DomainToken --> DomainTokenManager : delegates
    ValidateDomainToken --> DomainTokenManager : authenticate()
    DomainTokenManager --> DomainTokenModel : queries & persists
    DomainTokenManager --> ActionMatcher : evaluates actions
    DomainTokenManager --> IssuedToken : returns
    DomainTokenManager --> AuthenticatedDomainToken : returns
    GenerateDomainToken --> DomainToken : issue()
    HasDomainTokens ..|> TokenOwner : implements
    DomainTokenModel --> TokenOwner : owner implements
```

---

## Token Authentication Sequence

Detailed sequence for a request authenticated by `domain-token:{domain}` middleware.

```mermaid
sequenceDiagram
    participant Client
    participant Router
    participant Middleware as ValidateDomainToken
    participant Manager as DomainTokenManager
    participant DB as domain_tokens table
    participant BeeHive as TenantContext (optional)
    participant Controller

    Client->>Router: GET /resource (Bearer dtk_...)
    Router->>Middleware: handle(request, next, domain)
    Middleware->>Middleware: $request->bearerToken()
    alt No bearer token
        Middleware-->>Client: 401 {"message":"Token not found."}
    else Token present
        Middleware->>Manager: authenticate(plainToken, domain)
        Manager->>Manager: hash('sha256', plainToken)
        Manager->>DB: SELECT WHERE token_hash=? AND domain=?
        alt Token not found
            Manager-->>Middleware: throws TokenValidationException
            Middleware-->>Client: 401 {"message":"Token not found."}
        else Token found
            Manager->>Manager: isRevoked()? isWithinWindow()?
            alt Invalid
                Manager-->>Middleware: throws TokenValidationException
                Middleware-->>Client: 401 {"message":"..."}
            else Valid
                Manager->>DB: UPDATE last_used_at=now()
                Manager->>Manager: resolve polymorphic owner
                alt Owner not found
                    Manager-->>Middleware: throws TokenValidationException
                    Middleware-->>Client: 401 {"message":"Token owner not found."}
                else Owner found
                    Manager->>BeeHive: TenantContext::set(owner.tenant_id)
                    Manager-->>Middleware: AuthenticatedDomainToken
                    Middleware->>Middleware: $request->attributes->set(key, context)
                    Middleware->>Controller: $next($request)
                    Controller-->>Client: 200 response
                end
            end
        end
    end
```
