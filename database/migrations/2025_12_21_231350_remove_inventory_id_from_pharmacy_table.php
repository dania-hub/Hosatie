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
        Schema::table('pharmacy', function (Blueprint $table) {
            // 1️⃣ حذف Foreign Key أولاً
            $table->dropForeign(['inventory_id']);
            
            // 2️⃣ حذف العمود
            $table->dropColumn('inventory_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pharmacy', function (Blueprint $table) {
            // إعادة العمود
            $table->unsignedBigInteger('inventory_id')->nullable()->after('hospital_id');
            
            // إعادة Foreign Key
            $table->foreign('inventory_id')
                  ->references('id')
                  ->on('inventory')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }
};
