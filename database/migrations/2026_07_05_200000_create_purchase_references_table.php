<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema.md §2.13 — stub toward module R7 (achats). Holds only the
     * external reference; the full procurement module is out of R13 scope
     * (Phases.md Phase 10 turns this into a real integration point).
     */
    public function up(): void
    {
        Schema::create('purchase_references', function (Blueprint $table): void {
            $table->id();
            $table->string('external_purchase_id');
            $table->string('supplier')->nullable();
            $table->date('order_date')->nullable();
            $table->timestamps();

            $table->index('external_purchase_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_references');
    }
};
