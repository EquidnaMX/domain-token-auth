<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

        Schema::table($table, function (Blueprint $table): void {
            $table->string('tokenable_id', 64)->change();
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

        Schema::table($table, function (Blueprint $table): void {
            $table->string('tokenable_id')->change();
        });
    }
};
