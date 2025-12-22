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
        Schema::table('prescription', function (Blueprint $table) {
            // 1️⃣ حذف Foreign Key أولاً
            $table->dropForeign(['cancelled_by']);
            
            // 2️⃣ حذف العمود
            $table->dropColumn('cancelled_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescription', function (Blueprint $table) {
            // إعادة العمود
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            
            // إعادة Foreign Key
            $table->foreign('cancelled_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }
};
