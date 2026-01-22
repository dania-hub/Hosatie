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
            if (!Schema::hasColumn('external_supply_request_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('fulfilled_qty');
            }
            if (!Schema::hasColumn('external_supply_request_items', 'batch_number')) {
                $table->string('batch_number')->nullable()->after('expiry_date');
            }
        });

        Schema::table('internal_supply_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('internal_supply_request_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('fulfilled_qty');
            }
            if (!Schema::hasColumn('internal_supply_request_items', 'batch_number')) {
                $table->string('batch_number')->nullable()->after('expiry_date');
            }
        });

        Schema::table('inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('inventories', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('current_quantity');
            }
            if (!Schema::hasColumn('inventories', 'batch_number')) {
                $table->string('batch_number')->nullable()->after('expiry_date');
            }
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

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn(['expiry_date', 'batch_number']);
        });
    }
};
