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
            if (!Schema::hasColumn('external_supply_requests', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('external_supply_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_supply_requests', function (Blueprint $table) {
            if (Schema::hasColumn('external_supply_requests', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('external_supply_requests', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
};
