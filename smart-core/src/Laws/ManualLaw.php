<?php

namespace SmartCore\Laws;

/**
 * A law from config/laws.php that has no automatic check yet.
 */
final class ManualLaw implements Law
{
    public function __construct(private readonly string $code, private readonly string $title)
    {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function check(string $basePath): LawResult
    {
        return LawResult::manual();
    }
}
