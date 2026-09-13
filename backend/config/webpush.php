<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VAPID Keys (ProjectPlan.md §20.2 - Web Push authentication)
    |--------------------------------------------------------------------------
    |
    | Identify this application to push services (no third-party notification
    | vendor/account required). Generate a pair with:
    |   php -r "require 'vendor/autoload.php'; print_r(\Minishlink\WebPush\VAPID::createVapidKeys());"
    |
    */

    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),

];
