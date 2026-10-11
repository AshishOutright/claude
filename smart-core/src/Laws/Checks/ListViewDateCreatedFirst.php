<?php

namespace SmartCore\Laws\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SmartCore\Laws\Law;
use SmartCore\Laws\LawResult;

class ListViewDateCreatedFirst implements Law
{
    public function code(): string
    {
        return 'L-UI-001';
    }

    public function title(): string
    {
        return 'List views show Date Created as the first column, as relative time ("3 days ago")';
    }

    public function check(string $basePath): LawResult
    {
        $views = $basePath.'/resources/views';

        if (! is_dir($views)) {
            return LawResult::pass();
        }

        $problems = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if (preg_match('/<table\b/i', (string) file_get_contents($file->getPathname()))) {
                $relative = ltrim(substr($file->getPathname(), strlen($basePath)), '/');
                $problems[] = "{$relative} builds its own <table>; use <x-smartcore::list-view> so Date Created comes first.";
            }
        }

        return $problems === [] ? LawResult::pass() : LawResult::fail($problems);
    }
}
