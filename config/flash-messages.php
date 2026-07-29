<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Session key
    |--------------------------------------------------------------------------
    |
    | The session flash-bag key under which FlashMessage DTOs accumulate.
    |
    */

    'session_key' => 'flash.messages',

    /*
    |--------------------------------------------------------------------------
    | Resource metadata file
    |--------------------------------------------------------------------------
    |
    | Name of the application language file that declares per-resource
    | singular/plural/gender, e.g. `lang/{locale}/flash-resources.php`.
    |
    */

    'resources_key' => 'flash-resources',

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    'default_level' => 'success',

    'dismissible' => true,

    /*
    |--------------------------------------------------------------------------
    | Public API surface
    |--------------------------------------------------------------------------
    */

    'helper' => true,

    'macros' => true,

    /*
    |--------------------------------------------------------------------------
    | Inertia integration
    |--------------------------------------------------------------------------
    |
    | Shared under the `flashMessages` prop to stay clear of Inertia's own
    | `flash` event used by the non-CRUD toasts.
    |
    */

    'inertia' => [
        'enabled' => true,
        'prop' => 'flashMessages',
        'resolve' => true,
    ],

];
