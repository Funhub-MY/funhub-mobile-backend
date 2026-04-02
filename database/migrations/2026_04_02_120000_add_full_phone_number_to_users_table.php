<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'users_full_phone_number_index';

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'full_phone_number')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('full_phone_number', 64)->nullable()->after('phone_no');
                });
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'Duplicate column') && ! str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
            }
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("
                UPDATE users
                SET full_phone_number = NULLIF(
                    CONCAT(COALESCE(phone_country_code, ''), COALESCE(phone_no, '')),
                    ''
                )
            ");
        } else {
            DB::table('users')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $value = ($row->phone_country_code ?? '') . ($row->phone_no ?? '');
                    DB::table('users')->where('id', $row->id)->update([
                        'full_phone_number' => $value === '' ? null : $value,
                    ]);
                }
            });
        }

        if ($this->indexExists()) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->index('full_phone_number', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'full_phone_number')) {
            return;
        }

        if ($this->indexExists()) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(self::INDEX_NAME);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('full_phone_number');
        });
    }

    private function indexExists(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $table = Schema::getConnection()->getTablePrefix() . 'users';

            return collect(DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [self::INDEX_NAME]))->isNotEmpty();
        }

        if ($driver === 'sqlite') {
            $rows = DB::select(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = ? LIMIT 1",
                [self::INDEX_NAME]
            );

            return count($rows) > 0;
        }

        return false;
    }
};
