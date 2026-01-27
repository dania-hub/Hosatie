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
        Schema::table('internal_supply_requests', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('pharmacy_id')->constrained('suppliers')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('supplier_id')->constrained('departments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internal_supply_requests', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
