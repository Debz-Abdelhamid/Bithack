<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Faculty scoping for N2/N3 roles (Security.md §3 — query scoping driver)
            $table->foreignId('faculty_id')
                ->nullable()
                ->after('email')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Filament native app (TOTP) multi-factor authentication — values are
            // encrypted at the Eloquent cast level (Security.md §2/§7)
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('faculty_id');
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
