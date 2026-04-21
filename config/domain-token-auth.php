<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BeeHive Integration
    |--------------------------------------------------------------------------
    | Controls optional integration with equidna/bee-hive. Set
    | apply_tenant_context to false to prevent ATL from writing into the
    | TenantContext singleton after token authentication.
    */
    'bee_hive' => [
        'apply_tenant_context' => env('DOMAIN_TOKEN_APPLY_TENANT_CONTEXT', true),
        'enforce_tenant_isolation' => env('DOMAIN_TOKEN_ENFORCE_TENANT_ISOLATION', true),
    ],

    'token' => [
        'table' => 'domain_tokens',
        'prefix' => 'dtk_',
        'length' => 64,
        'default_ttl_minutes' => 60,
    ],

    'domains' => [
        'user' => [
            'model' => 'App\\Models\\User',
            'default_actions' => ['users.read'],
            'roles' => [
                'viewer' => ['users.read'],
                'admin' => ['users.*'],
            ],
            'default_ttl_minutes' => 60,
        ],

        'app' => [
            'model' => 'App\\Models\\Application',
            'default_actions' => ['apps.read'],
            'roles' => [
                'integrator' => ['apps.read', 'apps.write'],
                'owner' => ['apps.*'],
            ],
            'default_ttl_minutes' => 1440,
        ],
    ],
];
