<?php

return [
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
