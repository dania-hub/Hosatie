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
        Schema::table('external_supply_request_items', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('fulfilled_qty');
            $table->string('batch_number')->nullable()->after('expiry_date');
        });

        Schema::table('internal_supply_request_items', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('fulfilled_qty');
            $table->string('batch_number')->nullable()->after('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_supply_request_items', function (Blueprint $table) {
            $table->dropColumn(['expiry_date', 'batch_number']);
        });

        Schema::table('internal_supply_request_items', function (Blueprint $table) {
            $table->dropColumn(['expiry_date', 'batch_number']);
        });
    }
};
