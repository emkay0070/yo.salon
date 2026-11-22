<?php

use App\Domain\Platform\PlatformRole;

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Governance Role Assignments
    |--------------------------------------------------------------------------
    |
    | Lists of user emails mapped to approved platform governance roles.
    | Independent of Organization membership or Provider assignments.
    |
    */

    'roles' => [
        PlatformRole::PLATFORM_OWNER => array_filter(array_map('trim', explode(',', env('PLATFORM_OWNERS', '')))),
        PlatformRole::PLATFORM_SUPER_ADMIN => array_filter(array_map('trim', explode(',', env('PLATFORM_SUPER_ADMINS', '')))),
        PlatformRole::DEVELOPER => array_filter(array_map('trim', explode(',', env('PLATFORM_DEVELOPERS', '')))),
        PlatformRole::SUPPORT => array_filter(array_map('trim', explode(',', env('PLATFORM_SUPPORT', '')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Administrative Capabilities
    |--------------------------------------------------------------------------
    |
    | Fine-grained capability matrix mapping operational permissions to roles.
    |
    */

    'capabilities' => [
        // Seeder operations
        'seeder_view' => [
            PlatformRole::PLATFORM_OWNER,
            PlatformRole::PLATFORM_SUPER_ADMIN,
            PlatformRole::DEVELOPER,
        ],
        'seeder_execution' => [
            PlatformRole::PLATFORM_OWNER,
            PlatformRole::PLATFORM_SUPER_ADMIN,
        ],

        // Platform payment and invoice management
        'invoices_view' => [
            PlatformRole::PLATFORM_OWNER,
            PlatformRole::PLATFORM_SUPER_ADMIN,
            PlatformRole::DEVELOPER,
            PlatformRole::SUPPORT,
        ],
        'invoices_verify_reject' => [
            PlatformRole::PLATFORM_OWNER,
            PlatformRole::PLATFORM_SUPER_ADMIN,
        ],
        'payment_methods_manage' => [
            PlatformRole::PLATFORM_OWNER,
            PlatformRole::PLATFORM_SUPER_ADMIN,
        ],
        'payment_methods_view_details' => [
            PlatformRole::PLATFORM_OWNER,
            PlatformRole::PLATFORM_SUPER_ADMIN,
        ],

        // Schedule migration administration
        'schedule_migration_admin' => [
            PlatformRole::PLATFORM_OWNER,
            PlatformRole::PLATFORM_SUPER_ADMIN,
            PlatformRole::DEVELOPER,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Safeguards
    |--------------------------------------------------------------------------
    |
    | When set to true, database seeder execution through HTTP API endpoints
    | is strictly prohibited in production environments, regardless of role.
    |
    */

    'disable_seeders_in_production' => env('DISABLE_SEEDERS_IN_PRODUCTION', true),

];
