<?php

use Illuminate\Support\Str;

$targetDatabase = (string) env('TARGET_DB_DATABASE', database_path('database.sqlite'));
if ($targetDatabase !== ':memory:' && preg_match('~^(?:[A-Za-z]:[\\/]|[\\/])~', $targetDatabase) !== 1) {
    $targetDatabase = base_path($targetDatabase);
}

return [
    'default' => env('DB_CONNECTION', 'sqlsrv_ingco'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'sqlite_targets' => [
            'driver' => 'sqlite',
            'url' => env('TARGET_DATABASE_URL'),
            'database' => $targetDatabase,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
        ],

        'sqlsrv_ingco' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_INGCO'),
            'host' => env('DB_HOST_INGCO', '10.9.2.18'),
            'port' => env('DB_PORT_INGCO', '1433'),
            'database' => env('DB_DATABASE_INGCO', 'AS_INGCO'),
            'username' => env('DB_USERNAME_INGCO'),
            'password' => env('DB_PASSWORD_INGCO'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT_INGCO', 'no'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE_INGCO', 'true'),
        ],

        'sqlsrv_smi' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_SMI'),
            'host' => env('DB_HOST_SMI', '10.9.2.18'),
            'port' => env('DB_PORT_SMI', '1433'),
            'database' => env('DB_DATABASE_SMI', 'AS_SMI'),
            'username' => env('DB_USERNAME_SMI'),
            'password' => env('DB_PASSWORD_SMI'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT_SMI', 'no'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE_SMI', 'true'),
        ],

        'sqlsrv_apphub' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL_APPHUB'),
            // DB_AppHub berada di SQL Server yang sama dengan AS_INGCO.
            // Jika variable APPHUB tidak dibuat di .env, otomatis pakai koneksi INGCO existing.
            'host' => env('DB_HOST_APPHUB', env('DB_HOST_INGCO', '202.162.194.165')),
            'port' => env('DB_PORT_APPHUB', env('DB_PORT_INGCO', '5433')),
            'database' => env('DB_DATABASE_APPHUB', 'DB_AppHub'),
            'username' => env('DB_USERNAME_APPHUB', env('DB_USERNAME_INGCO')),
            'password' => env('DB_PASSWORD_APPHUB', env('DB_PASSWORD_INGCO')),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT_APPHUB', env('DB_ENCRYPT_INGCO', 'no')),
            'trust_server_certificate' => env(
                'DB_TRUST_SERVER_CERTIFICATE_APPHUB',
                env('DB_TRUST_SERVER_CERTIFICATE_INGCO', 'true')
            ),
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],
];
