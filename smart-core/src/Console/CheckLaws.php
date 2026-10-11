<?php

namespace SmartCore\Console;

use Illuminate\Console\Command;
use SmartCore\Laws\LawRegistry;

class CheckLaws extends Command
{
    protected $signature = 'laws:check';

    protected $description = 'Check the app against its laws; fails when a checked law is broken';

    public function handle(LawRegistry $registry): int
    {
        $failed = false;
        $rows = [];

        foreach ($registry->laws() as $law) {
            $result = $law->check(base_path());
            $failed = $failed || $result->failed();
            $rows[] = [$law->code(), $law->title(), strtoupper($result->status), implode("\n", $result->messages)];
        }

        $this->table(['Law', 'Title', 'Result', 'Details'], $rows);

        if ($failed) {
            $this->error('A law is broken. Fix it before merging.');

            return self::FAILURE;
        }

        $this->info('All checked laws pass.');

        return self::SUCCESS;
    }
}
