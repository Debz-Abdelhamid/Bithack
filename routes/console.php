<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phases.md Phase 7 DoD: SLA escalation "without manual polling" — the
// `scheduler` container (docker-compose.yml) runs `schedule:work` to fire this.
Schedule::command('patrimo:escalate-tickets')->everyFifteenMinutes();
