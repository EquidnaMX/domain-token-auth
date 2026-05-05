<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expands tokenable_id from VARCHAR(64) to VARCHAR(100) to support owners
 * with string primary keys longer than 64 characters (e.g. SHA-1 hashes).
 *
 * Run this migration on installations that already executed
 * 2026_04_28_000002_change_tokenable_id_to_varchar_64_on_domain_tokens_table.
 * Fresh installs get VARCHAR(100) directly from the create migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('domain-token-auth.token.table', 'domain_tokens');
        if ($table === '') {
            $table = 'domain_tokens';
        }

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tokenable_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        // SQLite does not support ALTER COLUMN; the column is already TEXT-compatible
        // so no-op is safe there.
        if ($driver === 'sqlite') {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->string('tokenable_id', 100)->change();
        });
    }

    public function down(): void
    {
        $table = (string) config('domain-token-auth.token.table', 'domain_tokens');
        if ($table === '') {
            $table = 'domain_tokens';
        }

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tokenable_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->string('tokenable_id', 64)->change();
        });
    }
};
