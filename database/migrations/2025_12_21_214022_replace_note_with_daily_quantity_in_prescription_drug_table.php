<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_drug', function (Blueprint $table) {
            // حذف عمود note
            $table->dropColumn('note');
            
            // إضافة daily_quantity
            $table->integer('daily_quantity')->nullable()->after('monthly_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_drug', function (Blueprint $table) {
            $table->dropColumn('daily_quantity');
            $table->text('note')->nullable()->after('monthly_quantity');
        });
    }
};
