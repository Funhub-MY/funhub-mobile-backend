<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ImportsFunhubCsvUsers;
use Illuminate\Database\Seeder;

/**
 * Seeds users from the "1000个标题库 - Create New User" sheet (exported to CSV).
 *
 * Source spreadsheet: export is stored at database/seeders/data/funhub_title_library_users.csv
 * Intended for mobile OTP login (phone + OTP); no password is stored.
 *
 * Re-runs are safe: any row whose phone_country_code + phone_no already exists (including soft-deleted)
 * is skipped; existing rows are not updated.
 */
class FunhubTitleLibraryUsersSeeder extends Seeder
{
    use ImportsFunhubCsvUsers;

    private const CSV_RELATIVE_PATH = 'seeders/data/funhub_title_library_users.csv';

    public function run(): void
    {
        $path = database_path(self::CSV_RELATIVE_PATH);
        $this->importFunhubUsersFromCsv($path, 0, 'Funhub title library users');
    }
}
