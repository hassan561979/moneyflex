<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded API account
    |--------------------------------------------------------------------------
    |
    | The account the seeder creates, used by the documentation and the request
    | collection. Read through config rather than calling env() at the point of
    | use: once the configuration is cached, env() returns null and the seeder
    | would quietly create an account with no address.
    |
    */

    'api_user' => [
        'email' => env('API_USER_EMAIL', 'api@moneyflex.test'),
        'password' => env('API_USER_PASSWORD', 'password123'),
    ],

];
