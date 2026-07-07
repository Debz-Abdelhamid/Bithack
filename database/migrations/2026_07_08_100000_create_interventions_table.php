<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema.md §2.9 — planned/carried-out work against a ticket (Étape 5).
     * `technician_id` is a plain `users` FK (Service technique membership,
     * not a dedicated role — Security.md §3).
     */
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_ticket_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('technician_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('report')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('status')->default('planned'); // App\Enums\InterventionStatus
            $table->timestamps();

            $table->index('maintenance_ticket_id');
            $table->index('technician_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
