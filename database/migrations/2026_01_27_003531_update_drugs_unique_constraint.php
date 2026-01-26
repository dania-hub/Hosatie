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
        Schema::table('drugs', function (Blueprint $table) {
            // Drop the existing unique constraint
            $table->dropUnique('drugs_name_generic_name_unique');

            // Add a new unique constraint including the strength
            $table->unique(['name', 'generic_name', 'strength'], 'drugs_name_generic_strength_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            // Revert changes
            $table->dropUnique('drugs_name_generic_strength_unique');
            $table->unique(['name', 'generic_name'], 'drugs_name_generic_name_unique');
        });
    }
};
