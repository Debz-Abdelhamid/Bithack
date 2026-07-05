<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema.md §2.4 — one opaque QR token per trackable asset (equipment
     * or local). The token is what the printed QR encodes and what the
     * public lookup resolves; sequential DB ids are never exposed
     * (Security.md / ui-design.md §9.3).
     */
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('trackable_type');
            $table->unsignedBigInteger('trackable_id');
            $table->uuid('token')->unique();
            $table->timestamp('generated_at');
            $table->boolean('printed')->default(false);
            $table->timestamps();

            // One QR per asset (ERD: ||--o|) — the unique pair doubles as
            // the morph lookup index.
            $table->unique(['trackable_type', 'trackable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
