<?php

namespace App\Services\Builder;

use Illuminate\Support\Str;

/**
 * Turns names typed in the wizard into class names and keys for generated code.
 */
class Naming
{
    public static function className(string $name): string
    {
        $studly = Str::studly(preg_replace('/[^A-Za-z0-9]+/', ' ', Str::ascii($name)));

        return preg_match('/^[0-9]/', $studly) ? 'N'.$studly : $studly;
    }

    public static function key(string $name): string
    {
        return Str::snake(self::className($name));
    }
}
