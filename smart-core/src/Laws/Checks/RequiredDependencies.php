<?php

namespace SmartCore\Laws\Checks;

use SmartCore\Laws\Law;
use SmartCore\Laws\LawResult;

class RequiredDependencies implements Law
{
    public function code(): string
    {
        return 'L-CODE-001';
    }

    public function title(): string
    {
        return 'A record cannot be saved without the records it depends on';
    }

    public function check(string $basePath): LawResult
    {
        return LawResult::runtime('Models list their dependencies in $requires and use SmartCore\\Laws\\RequiresDependencies, which blocks the save.');
    }
}
