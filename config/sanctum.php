<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Since you are NOT using cookie/CSRF auth, you can leave this empty.
    |
    */
    'stateful' => [],

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Sanctum will check these guards when authenticating.
    | Keep "sanctum" so your `auth:sanctum` middleware works.
    |
    */
    'guard' => ['sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Null = tokens never expire (unless revoked).
    | You can set an integer if you want auto-expiry.
    |
    */
    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Optional prefix for tokens (e.g. "laravel_").
    | Default is no prefix.
    |
    */
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | These are only needed for cookie-based SPA auth.
    | You can safely remove them in token-only mode.
    |
    */
    'middleware' => [],

];
