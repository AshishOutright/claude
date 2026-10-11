<?php

return [

    // Password for the builder's login page. Leave empty only on your own machine.
    'password' => env('BUILDER_PASSWORD'),

    // Smart Core, whose framework-free parts are copied into every generated Smart Gateway.
    'smart_core_path' => env('SMART_CORE_PATH', base_path('vendor/outright/smart-core')),

];
