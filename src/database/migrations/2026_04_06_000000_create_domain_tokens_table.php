<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('domain_tokens')) {
            return;
        }

        Schema::create('domain_tokens', function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->string('domain', 64);
            $table->string('name')->nullable();
            $table->json('roles')->nullable();
            $table->json('actions')->nullable();
            $table->nullableMorphs('tokenable');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();

            $table->index(['domain', 'revoked_at']);
            $table->index(['domain', 'starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_tokens');
    }
};
