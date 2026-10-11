<?php

namespace SmartCore\Laws;

/**
 * Runs every enabled law against a project folder. Used by `php artisan laws:check`
 * and by the generated apps' `php bin/laws-check.php`.
 */
class LawRunner
{
    public function __construct(private readonly LawRegistry $registry)
    {
    }

    /** @return list<array{law: Law, result: LawResult}> */
    public function run(string $basePath): array
    {
        return array_map(
            fn (Law $law) => ['law' => $law, 'result' => $law->check($basePath)],
            $this->registry->laws(),
        );
    }

    /** @param list<array{law: Law, result: LawResult}> $results */
    public static function failed(array $results): bool
    {
        foreach ($results as $row) {
            if ($row['result']->failed()) {
                return true;
            }
        }

        return false;
    }

    /** Plain-text report for the command line. */
    public static function report(array $results): string
    {
        $lines = [];

        foreach ($results as $row) {
            $lines[] = sprintf('%-8s %-11s %s', strtoupper($row['result']->status), $row['law']->code(), $row['law']->title());

            foreach ($row['result']->messages as $message) {
                $lines[] = '         '.str_repeat(' ', 12).'- '.$message;
            }
        }

        $lines[] = '';
        $lines[] = self::failed($results) ? 'A law is broken. Fix it before merging.' : 'All checked laws pass.';

        return implode("\n", $lines)."\n";
    }
}
