<?php

namespace SmartCore\Laws;

final class LawResult
{
    public const PASS = 'pass';
    public const FAIL = 'fail';
    public const RUNTIME = 'runtime';
    public const MANUAL = 'manual';

    /** @param list<string> $messages */
    private function __construct(public readonly string $status, public readonly array $messages = [])
    {
    }

    public static function pass(): self
    {
        return new self(self::PASS);
    }

    /** @param list<string> $messages */
    public static function fail(array $messages): self
    {
        return new self(self::FAIL, $messages);
    }

    /** Enforced by core code while the app runs, not by a static check. */
    public static function runtime(string $how): self
    {
        return new self(self::RUNTIME, [$how]);
    }

    /** Reviewed by people on each pull request. */
    public static function manual(): self
    {
        return new self(self::MANUAL);
    }

    public function failed(): bool
    {
        return $this->status === self::FAIL;
    }
}
