<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 addendum (2026-07-06, owner decision): the academic year is
     * split into 2 semesters; a faculty fills its departments' timetable
     * for one semester at a time. This replaces the previous per-slot
     * "repeat until" date picker (arbitrary, capped at N months) — a
     * recurring timetable slot now runs to its term's end_date, the
     * authoritative boundary.
     */
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('academic_year'); // e.g. "2026-2027"
            $table->unsignedTinyInteger('semester'); // 1 or 2
            $table->string('label'); // e.g. "2026-2027 — Semester 1"
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['academic_year', 'semester']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};
