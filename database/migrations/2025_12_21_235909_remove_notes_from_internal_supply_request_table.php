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
        Schema::table('internal_supply_request', function (Blueprint $table) {
            // 1️⃣ حذف عمود notes
            $table->dropColumn('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internal_supply_request', function (Blueprint $table) {
            // إعادة العمود
            $table->text('notes')->nullable()->before('approved_by');
        });
    }
};
