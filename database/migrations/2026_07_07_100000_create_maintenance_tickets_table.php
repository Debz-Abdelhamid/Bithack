<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema.md §2.8 — signalement/maintenance ticket (Étape 4). A ticket
     * targets an equipment, a room, or both (Assignment's subject-required
     * pattern reused: "ticket can target a room without one specific
     * asset"). `sla_due_at` is computed at creation (MaintenanceTicketObserver,
     * §4) — never edited by hand.
     */
    public function up(): void
    {
        Schema::create('maintenance_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
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
            $table->foreignId('reported_by_user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('source')->default('manual'); // App\Enums\TicketSource
            $table->text('description');
            $table->string('priority'); // App\Enums\TicketPriority
            $table->timestamp('sla_due_at');
            $table->string('category')->nullable();
            $table->string('status')->default('new'); // App\Enums\TicketStatus
            $table->foreignId('assigned_service_id')
                ->nullable()
                ->constrained('services')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();

            // Schema.md §0 (FKs indexed) + §5 (escalation scheduler index).
            $table->index('equipment_id');
            $table->index('local_id');
            $table->index('reported_by_user_id');
            $table->index('assigned_service_id');
            $table->index(['status', 'sla_due_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE maintenance_tickets ADD CONSTRAINT maintenance_tickets_subject_check '
                .'CHECK (equipment_id IS NOT NULL OR local_id IS NOT NULL)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};
