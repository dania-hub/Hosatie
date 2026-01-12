<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('external_supply_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('external_supply_requests', 'priority')) {
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_supply_requests', function (Blueprint $table) {
             if (Schema::hasColumn('external_supply_requests', 'priority')) {
                $table->dropColumn('priority');
            }
        });
    }
};
