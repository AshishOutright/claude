<?php

namespace SmartCore\Laws\Checks;

use SmartCore\Laws\Law;
use SmartCore\Laws\LawResult;

class NoRouteClosures implements Law
{
    public function code(): string
    {
        return 'L-CODE-002';
    }

    public function title(): string
    {
        return 'All code lives in classes and methods; routes point at controllers';
    }

    public function check(string $basePath): LawResult
    {
        $problems = [];

        foreach (['routes/web.php', 'routes/api.php'] as $file) {
            $path = $basePath.'/'.$file;

            if (! is_file($path)) {
                continue;
            }

            foreach (file($path) as $number => $line) {
                if (preg_match('/Route::(get|post|put|patch|delete|options|any|match)\s*\(.*\b(function|fn)\s*\(/i', $line)) {
                    $problems[] = $file.':'.($number + 1).' uses a closure; move it into a controller method.';
                }
            }
        }

        return $problems === [] ? LawResult::pass() : LawResult::fail($problems);
    }
}
