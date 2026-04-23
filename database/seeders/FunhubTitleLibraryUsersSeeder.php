<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Laravel\Scout\ModelObserver;

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
    private const CSV_RELATIVE_PATH = 'seeders/data/funhub_title_library_users.csv';

    /** Emails are `{email_local}@funhub.my` so each seed id stays unique. */
    private const EMAIL_DOMAIN = 'funhub.my';

    public function run(): void
    {
        $path = database_path(self::CSV_RELATIVE_PATH);

        if (! is_readable($path)) {
            $this->command?->error("Missing or unreadable CSV: {$path}");

            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->command?->error("Could not open CSV: {$path}");

            return;
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                $this->command?->error('CSV is empty.');

                return;
            }

            $verifiedAt = now();
            $created = 0;
            $skipped = 0;

            ModelObserver::disableSyncingFor(User::class);

            try {
                while (($row = fgetcsv($handle)) !== false) {
                    if ($this->rowIsEmpty($row)) {
                        continue;
                    }

                    $data = array_combine($header, $row);
                    if ($data === false) {
                        continue;
                    }

                    $displayName = trim((string) ($data['display_name'] ?? ''));
                    $username = trim((string) ($data['username'] ?? ''));
                    $emailLocal = trim((string) ($data['email_local'] ?? ''));
                    $phoneNo = $this->normalizeDigits((string) ($data['phone_no'] ?? ''));
                    $countryCode = $this->normalizeDigits((string) ($data['country_code'] ?? ''));

                    if ($phoneNo === '' || $countryCode === '' || $username === '' || $emailLocal === '') {
                        $this->command?->warn('Skipping row with missing phone, country_code, username, or email_local: '.json_encode($row, JSON_UNESCAPED_UNICODE));

                        continue;
                    }

                    if (User::withTrashed()->where('phone_country_code', $countryCode)->where('phone_no', $phoneNo)->exists()) {
                        $skipped++;

                        continue;
                    }

                    $email = strtolower($emailLocal).'@'.self::EMAIL_DOMAIN;

                    if (User::withTrashed()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
                        $skipped++;
                        $this->command?->warn("Skipping row: email already in use: {$email}");

                        continue;
                    }

                    try {
                        User::create([
                            'phone_country_code' => $countryCode,
                            'phone_no' => $phoneNo,
                            'username' => $username,
                            'name' => $displayName !== '' ? $displayName : $username,
                            'email' => $email,
                            'email_verified_at' => $verifiedAt,
                            'otp_verified_at' => $verifiedAt,
                            'status' => User::STATUS_ACTIVE,
                            'account_restricted' => false,
                        ]);
                        $created++;
                    } catch (QueryException $e) {
                        if ($this->isDuplicateKeyException($e)) {
                            $skipped++;

                            continue;
                        }
                        throw $e;
                    }
                }
            } finally {
                ModelObserver::enableSyncingFor(User::class);
            }

            $this->command?->info("Funhub title library users: created {$created}, skipped (already present or email conflict) {$skipped}.");
        } finally {
            fclose($handle);
        }
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeDigits(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            $value = (string) (int) (float) $value;
        }

        return $value;
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        return str_contains(strtolower($e->getMessage()), 'duplicate')
            || $driverCode === 1062
            || $driverCode === 19;
    }
}
