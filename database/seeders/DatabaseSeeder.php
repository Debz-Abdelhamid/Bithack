<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Demo accounts/referential data — fake by construction, safe locally.
        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
