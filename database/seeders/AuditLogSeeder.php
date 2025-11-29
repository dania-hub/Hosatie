<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        // Insert a single, harmless audit log row so DatabaseSeeder doesn't fail.
        DB::table('audit_log')->insert([
            'user_id'    => null,
            'action'     => 'seed:init',
            'table_name' => 'seeders',
            'record_id'  => null,
            'old_values' => null,
            'new_values' => 'Initial seed created',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);
    }
}
