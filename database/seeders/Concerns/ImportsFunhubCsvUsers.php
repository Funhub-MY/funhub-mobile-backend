<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use Illuminate\Database\QueryException;
use Laravel\Scout\ModelObserver;

trait ImportsFunhubCsvUsers
{
    private const EMAIL_DOMAIN = 'funhub.my';

    /**
     * Import users from a CSV with columns display_name, username, email_local (or full email), phone_no, country_code.
     *
     * Re-runs are safe: rows whose phone_country_code + phone_no or email already exist (including soft-deleted) are skipped.
     *
     * @param  int  $skipRowsBeforeHeader  Number of leading rows to discard before the header row (e.g. 1 for a "Table 1" line).
     */
    protected function importFunhubUsersFromCsv(string $path, int $skipRowsBeforeHeader = 0, ?string $logLabel = null): void
    {
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
            for ($s = 0; $s < $skipRowsBeforeHeader; $s++) {
                if (fgetcsv($handle) === false) {
                    $this->command?->error('CSV ended before header row.');

                    return;
                }
            }

            $headerRaw = fgetcsv($handle);
            if ($headerRaw === false) {
                $this->command?->error('CSV is empty.');

                return;
            }

            $keysByIndex = $this->funhubCsvHeaderKeysByIndex($headerRaw);
            if ($keysByIndex === []) {
                $this->command?->error('CSV header has no usable columns.');

                return;
            }

            $verifiedAt = now();
            $created = 0;
            $skipped = 0;

            ModelObserver::disableSyncingFor(User::class);

            try {
                while (($row = fgetcsv($handle)) !== false) {
                    if ($this->funhubCsvRowIsEmpty($row)) {
                        continue;
                    }

                    $data = $this->funhubCsvRowToAssoc($keysByIndex, $row);

                    $displayName = trim((string) ($data['display_name'] ?? ''));
                    $username = trim((string) ($data['username'] ?? ''));
                    $emailCell = trim((string) ($data['email_local'] ?? ''));
                    $phoneNo = $this->funhubCsvNormalizeDigits((string) ($data['phone_no'] ?? ''));
                    $countryCode = $this->funhubCsvNormalizeDigits((string) ($data['country_code'] ?? ''));

                    if ($phoneNo === '' || $countryCode === '' || $username === '' || $emailCell === '') {
                        $this->command?->warn('Skipping row with missing phone, country_code, username, or email: '.json_encode($row, JSON_UNESCAPED_UNICODE));

                        continue;
                    }

                    $email = $this->funhubCsvResolveEmail($emailCell);

                    if (User::withTrashed()->where('phone_country_code', $countryCode)->where('phone_no', $phoneNo)->exists()) {
                        $skipped++;

                        continue;
                    }

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
                        if ($this->funhubCsvIsDuplicateKeyException($e)) {
                            $skipped++;

                            continue;
                        }
                        throw $e;
                    }
                }
            } finally {
                ModelObserver::enableSyncingFor(User::class);
            }

            $label = $logLabel ?? basename($path);
            $this->command?->info("{$label}: created {$created}, skipped (already present or email conflict) {$skipped}.");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string|null>  $headerRaw
     * @return array<int, string> index => normalized column key
     */
    private function funhubCsvHeaderKeysByIndex(array $headerRaw): array
    {
        $keysByIndex = [];
        foreach ($headerRaw as $i => $rawName) {
            $key = strtolower(trim((string) $rawName));
            if ($key === '' || $key === 'user id') {
                continue;
            }
            $keysByIndex[$i] = $key;
        }

        return $keysByIndex;
    }

    /**
     * @param  array<int, string>  $keysByIndex
     * @param  list<string|null>  $row
     * @return array<string, string>
     */
    private function funhubCsvRowToAssoc(array $keysByIndex, array $row): array
    {
        $data = [];
        foreach ($keysByIndex as $i => $key) {
            $data[$key] = isset($row[$i]) && $row[$i] !== null ? (string) $row[$i] : '';
        }

        return $data;
    }

    private function funhubCsvResolveEmail(string $emailCell): string
    {
        $emailCell = strtolower(trim($emailCell));
        if (str_contains($emailCell, '@')) {
            return $emailCell;
        }

        return $emailCell.'@'.self::EMAIL_DOMAIN;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function funhubCsvRowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function funhubCsvNormalizeDigits(string $value): string
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

    private function funhubCsvIsDuplicateKeyException(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        return str_contains(strtolower($e->getMessage()), 'duplicate')
            || $driverCode === 1062
            || $driverCode === 19;
    }
}
