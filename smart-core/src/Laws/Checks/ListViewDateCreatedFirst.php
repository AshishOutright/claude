<?php

namespace SmartCore\Laws\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SmartCore\Laws\Law;
use SmartCore\Laws\LawResult;

/**
 * Checks list screens in all three places an app can have them:
 * CRM list view metadata, React screens and Blade views.
 */
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
        $problems = array_merge(
            $this->checkCrmListViews($basePath.'/crm/modules', $basePath),
            $this->checkFiles($basePath.'/frontend/src', $basePath, ['.jsx', '.tsx', '.js'], 'ListView.jsx', 'use the <ListView> component'),
            $this->checkFiles($basePath.'/resources/views', $basePath, ['.blade.php'], null, 'use <x-smartcore::list-view>'),
        );

        return $problems === [] ? LawResult::pass() : LawResult::fail($problems);
    }

    /** @return list<string> */
    private function checkCrmListViews(string $modules, string $basePath): array
    {
        $problems = [];

        foreach (glob($modules.'/*/metadata/listviewdefs.php') ?: [] as $file) {
            $listViewDefs = [];
            include $file;
            $defs = reset($listViewDefs);

            if (! is_array($defs) || array_key_first($defs) !== 'DATE_ENTERED') {
                $problems[] = $this->relative($file, $basePath).' must list DATE_ENTERED first.';
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function checkFiles(string $dir, string $basePath, array $extensions, ?string $allowed, string $fix): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $problems = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $name = $file->getFilename();
            $matches = array_filter($extensions, fn ($ext) => str_ends_with($name, $ext));

            if ($matches === [] || $name === $allowed) {
                continue;
            }

            if (preg_match('/<table\b/i', (string) file_get_contents($file->getPathname()))) {
                $problems[] = $this->relative($file->getPathname(), $basePath)." builds its own <table>; {$fix} so Date Created comes first.";
            }
        }

        return $problems;
    }

    private function relative(string $path, string $basePath): string
    {
        return ltrim(substr($path, strlen($basePath)), '/');
    }
}
