<?php

// The builder keeps the same laws it hands to new apps. Run: php artisan laws:check
return [

    'enabled' => [
        'L-UI-001' => 'List views show Date Created as the first column, as relative time ("3 days ago")',
        'L-CODE-002' => 'All code lives in classes and methods; routes point at controllers',
        'L-SEC-001' => 'Security lives in the app; .htaccess only routes requests',
    ],

];
