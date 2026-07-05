<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema.md §2.6 — affectations (Étape 2). Subject is an equipment, a
     * whole room, or an equipment moving into a room ("affectation d'un
     * bien à un service/local/personne", §1); at least one of the two is
     * required. History rows are never overwritten — a new active
     * assignment closes the previous one (observer), so restrict-on-delete
     * everywhere per §0.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipment_id')
                ->nullable()
                ->constrained('equipments')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('local_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('service_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Schema.md §0 (FKs indexed) + active-assignment lookups.
            $table->index(['equipment_id', 'end_date']);
            $table->index(['local_id', 'end_date']);
            $table->index('service_id');
            $table->index('assigned_to_user_id');
            $table->index('assigned_by_user_id');
        });

        // Defense-in-depth on Postgres (sqlite can't ALTER ADD CONSTRAINT;
        // the form/app validation enforces the same rule on every driver).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE assignments ADD CONSTRAINT assignments_subject_check '
                .'CHECK (equipment_id IS NOT NULL OR local_id IS NOT NULL)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
