<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema.md §2.3 — biens / équipements. Restrict-on-delete per §0
     * (assets are never hard-deleted casually — status covers the
     * lifecycle: in_service, under_repair, decommissioned, lost).
     * `sub_category` is nullable (documented relaxation of §2.3 — not
     * every asset has a sub-category).
     */
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table): void {
            $table->id();
            $table->string('inventory_code')->unique();
            $table->string('designation');
            $table->string('category');
            $table->string('sub_category')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->foreignId('local_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_value', 12, 2)->nullable();
            $table->foreignId('purchase_reference_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('warranty_end_date')->nullable();
            $table->string('status')->default('in_service'); // App\Enums\EquipmentStatus
            $table->string('condition')->default('good'); // App\Enums\EquipmentCondition
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Schema.md §0 (FKs indexed — Postgres does not auto-index FK
            // columns) + §5 (list filters on status/category).
            $table->index('local_id');
            $table->index('purchase_reference_id');
            $table->index('status');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};
