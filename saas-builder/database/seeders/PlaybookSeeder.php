<?php

namespace Database\Seeders;

use App\Models\Constitution;
use App\Models\Law;
use Illuminate\Database\Seeder;

/**
 * Loads the base constitutions and laws from config/playbook.php. Safe to re-run.
 */
class PlaybookSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('playbook.constitutions') as $row) {
            Constitution::updateOrCreate(['code' => $row['code']], $row);
        }

        foreach (config('playbook.laws') as $row) {
            Law::updateOrCreate(['code' => $row['code']], $row);
        }
    }
}
