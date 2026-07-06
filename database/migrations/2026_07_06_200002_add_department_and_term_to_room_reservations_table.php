<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 addendum (2026-07-06) — see the departments/academic_terms
     * migrations. `department` (free text) is replaced by `department_id`;
     * `academic_term_id` is new. Both nullable at the DB level (an ad-hoc,
     * non-course `request` may have neither) but required by the form for
     * `source = timetable` rows and course-linked bookings.
     */
    public function up(): void
    {
        Schema::table('room_reservations', function (Blueprint $table): void {
            $table->dropColumn('department');

            $table->foreignId('department_id')
                ->nullable()
                ->after('local_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('academic_term_id')
                ->nullable()
                ->after('recurring_group_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('academic_term_id');
            $table->string('department')->nullable();
        });
    }
};
