<?php

namespace SmartCore\Laravel;

use Illuminate\Console\Command;
use SmartCore\Laws\LawRegistry;
use SmartCore\Laws\LawRunner;

class CheckLaws extends Command
{
    protected $signature = 'laws:check';

    protected $description = 'Check the app against its laws; fails when a checked law is broken';

    public function handle(LawRegistry $registry): int
    {
        $results = (new LawRunner($registry))->run(base_path());

        $this->table(['Law', 'Title', 'Result', 'Details'], array_map(fn ($row) => [
            $row['law']->code(),
            $row['law']->title(),
            strtoupper($row['result']->status),
            implode("\n", $row['result']->messages),
        ], $results));

        if (LawRunner::failed($results)) {
            $this->error('A law is broken. Fix it before merging.');

            return self::FAILURE;
        }

        $this->info('All checked laws pass.');

        return self::SUCCESS;
    }
}
