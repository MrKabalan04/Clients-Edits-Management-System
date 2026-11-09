<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial Administrator
    |--------------------------------------------------------------------------
    |
    | These values are used by the AdminSeeder to create or update the first
    | administrator account. Keep the password out of source control and set
    | the matching variables in your local .env file.
    |
    */

    'name' => env('ADMIN_NAME'),

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

];
