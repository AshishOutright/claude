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
        return LawResult::runtime('The Smart Gateway rejects a create or update when a required related record is missing.');
    }
}
