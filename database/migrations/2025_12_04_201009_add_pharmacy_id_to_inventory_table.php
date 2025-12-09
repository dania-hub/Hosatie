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
        Schema::table('inventory', function (Blueprint $table) {
            // إضافة عمود pharmacy_id بعد warehouse_id
            $table->unsignedBigInteger('pharmacy_id')->nullable()->after('warehouse_id');

            // إضافة مفتاح خارجي (Foreign Key)
            $table->foreign('pharmacy_id')->references('id')->on('pharmacy')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            // حذف المفتاح الخارجي أولاً
            $table->dropForeign(['pharmacy_id']);
            
            // ثم حذف العمود
            $table->dropColumn('pharmacy_id');
        });
    }
};
