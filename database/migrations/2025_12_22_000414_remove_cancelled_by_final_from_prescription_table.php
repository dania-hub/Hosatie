<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ Raw SQL - يحذف FK حتى لو الاسم مختلف
        DB::statement('ALTER TABLE prescription DROP FOREIGN KEY IF EXISTS prescription_cancelled_by_foreign');
        DB::statement('ALTER TABLE prescription DROP COLUMN IF EXISTS cancelled_by');
    }

    public function down(): void
    {
        Schema::table('prescription', function ($table) {
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
        });
    }
};
