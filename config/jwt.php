<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Signing key
    |--------------------------------------------------------------------------
    |
    | Falls back to the application key so the stack works out of the box, but
    | a deployment should set a dedicated JWT_SECRET: rotating one secret then
    | does not invalidate everything else the application key protects.
    |
    */

    // Note the ?: rather than a default argument: an empty JWT_SECRET= line
    // yields an empty string, which would otherwise satisfy the default and
    // leave the application with no signing key at all.
    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),

    'algorithm' => env('JWT_ALGORITHM', 'HS256'),

    /*
    |--------------------------------------------------------------------------
    | Lifetimes
    |--------------------------------------------------------------------------
    |
    | ttl is how long an access token stays valid, in minutes. refresh_ttl is
    | how long after issuing a token it may still be exchanged for a new one.
    |
    */

    'ttl' => (int) env('JWT_TTL', 60),

    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 20160),

    /*
    |--------------------------------------------------------------------------
    | Claims
    |--------------------------------------------------------------------------
    */

    'issuer' => env('JWT_ISSUER', env('APP_URL', 'moneyflex')),

    /*
    |--------------------------------------------------------------------------
    | Leeway
    |--------------------------------------------------------------------------
    |
    | Seconds of clock skew tolerated when verifying time based claims.
    |
    */

    'leeway' => (int) env('JWT_LEEWAY', 10),

];
