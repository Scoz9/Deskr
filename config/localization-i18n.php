<?php

use Scrapkit\LocalizationI18n\Resolvers\AcceptLanguageResolver;
use Scrapkit\LocalizationI18n\Resolvers\RequestParameterResolver;
use Scrapkit\LocalizationI18n\Resolvers\SessionResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | The default locale is used when no other locale can be resolved from
    | the request. The fallback locale is used by Laravel's translator when
    | a key is missing in the active locale. Adding a new language to the
    | application only requires adding it to "supported_locales" and
    | creating the matching lang/{locale} files (see translations:add-locale).
    |
    */

    'default_locale' => 'it',

    'fallback_locale' => 'en',

    'supported_locales' => [
        'it',
        'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale Resolution
    |--------------------------------------------------------------------------
    |
    | The SetLocale middleware walks the resolvers below in order and uses
    | the first supported locale returned. Each resolver must implement
    | Scrapkit\LocalizationI18n\Contracts\LocaleResolver, so applications
    | can prepend their own (e.g. a database user-preference resolver)
    | without touching the package.
    |
    */

    'request_parameter' => 'locale',

    'session_key' => 'app_locale',

    'resolvers' => [
        RequestParameterResolver::class,
        SessionResolver::class,
        AcceptLanguageResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale Switch Route
    |--------------------------------------------------------------------------
    |
    | When enabled, the package registers PUT /locale to persist a new
    | locale in the session. The frontend useLocale() hook targets it.
    |
    */

    'routes' => [
        'switch_enabled' => true,
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime Translations API (optional)
    |--------------------------------------------------------------------------
    |
    | Disabled by default: the recommended delivery is build-time JSON via
    | translations:generate. Enable this to serve GET {prefix}/{locale}/{ns}
    | for runtime consumption (e.g. i18next-http-backend).
    |
    */

    'api' => [
        'enabled' => false,
        'prefix' => 'api/translations',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Export
    |--------------------------------------------------------------------------
    |
    | translations:generate converts lang/{locale}/{namespace}.php files
    | into i18next-ready JSON. "namespaces" limits which files are exported
    | (null = every file except "exclude"). "generate_types" also emits a
    | TypeScript resources.d.ts built from the default locale.
    |
    */

    'frontend' => [
        'output_path' => resource_path('js/locales'),
        'namespaces' => null,
        'exclude' => ['validation', 'passwords', 'pagination', 'auth'],
        'default_namespace' => 'common',
        'generate_types' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Inertia Shared Props (optional)
    |--------------------------------------------------------------------------
    |
    | When Inertia is installed and "share" is true, the package shares the
    | current locale and the supported locales under the given prop key.
    |
    */

    'inertia' => [
        'share' => true,
        'key' => 'localization',
    ],

    /*
    |--------------------------------------------------------------------------
    | Unused Key Scanning
    |--------------------------------------------------------------------------
    |
    | Paths (relative to base_path) scanned by translations:unused for
    | __(), trans(), @lang and t() usages.
    |
    */

    'scan_paths' => [
        'app',
        'resources/views',
        'resources/js',
    ],

];
