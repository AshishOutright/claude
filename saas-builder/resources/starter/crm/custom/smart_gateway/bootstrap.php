<?php

// Loads Smart Core (core/) and this app's classes (app/). Works inside the CRM and from the command line.
spl_autoload_register(static function (string $class): void {
    foreach (['SmartCore\\' => __DIR__.'/core/', 'SmartApp\\' => __DIR__.'/app/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
