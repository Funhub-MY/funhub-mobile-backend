<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ImportsFunhubCsvUsers;
use Illuminate\Database\Seeder;

/**
 * Seeds users from Visit Malaysia batch 2 only (does not read batch 1 CSV).
 *
 * Source: database/seeders/data/Visit Malaysia_New User List_Batch 2.csv
 *
 * Run: php artisan db:seed --class=VisitMalaysiaBatch2UsersSeeder
 *
 * Re-runs are safe: users already present (same phone or email) are skipped; batch 1 users are unchanged.
 */
class VisitMalaysiaBatch2UsersSeeder extends Seeder
{
    use ImportsFunhubCsvUsers;

    private const CSV_RELATIVE_PATH = 'seeders/data/Visit Malaysia_New User List_Batch 2.csv';

    public function run(): void
    {
        $path = database_path(self::CSV_RELATIVE_PATH);
        $this->importFunhubUsersFromCsv($path, 1, 'Visit Malaysia batch 2 users');
    }
}
