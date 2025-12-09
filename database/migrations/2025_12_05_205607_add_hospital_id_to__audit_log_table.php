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
        Schema::table('audit_log', function (Blueprint $table) {
    $table->unsignedBigInteger('hospital_id')->nullable()->after('user_id');
    $table->foreign('hospital_id')->references('id')->on('hospital')->onDelete('set null');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('_audit_log', function (Blueprint $table) {
            //
        });
    }
};
