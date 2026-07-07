<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7 — idempotency guard for the SLA-escalation job (Phases.md
     * Phase 7 DoD): a ticket is notified-as-escalated at most once, not on
     * every scheduler tick.
     */
    public function up(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table): void {
            $table->timestamp('escalated_at')->nullable()->after('sla_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table): void {
            $table->dropColumn('escalated_at');
        });
    }
};
