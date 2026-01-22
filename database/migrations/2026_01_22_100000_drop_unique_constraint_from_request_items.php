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
        // 1. Fix external_supply_request_items
        Schema::table('external_supply_request_items', function (Blueprint $table) {
            // Drop foreign keys first because they might depend on the unique index
            $table->dropForeign(['request_id']); 
            $table->dropForeign(['drug_id']);
            
            // Now safe to drop the unique index
            $table->dropUnique(['request_id', 'drug_id']);
            
            // Re-add the foreign keys
            $table->foreign('request_id')->references('id')->on('external_supply_requests')->onDelete('cascade');
            $table->foreign('drug_id')->references('id')->on('drugs');
        });

        // 2. Fix internal_supply_request_items
        Schema::table('internal_supply_request_items', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['request_id']);
            $table->dropForeign(['drug_id']);
            
            // Drop unique index
            $table->dropUnique(['request_id', 'drug_id']);
            
            // Re-add foreign keys
            $table->foreign('request_id')->references('id')->on('internal_supply_requests')->onDelete('cascade');
            $table->foreign('drug_id')->references('id')->on('drugs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore unique constraints
        // Note: This might fail if duplicate data was created while the constraint was missing.
        
        Schema::table('external_supply_request_items', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
            $table->dropForeign(['drug_id']);
            
            $table->unique(['request_id', 'drug_id']);
            
            $table->foreign('request_id')->references('id')->on('external_supply_requests')->onDelete('cascade');
            $table->foreign('drug_id')->references('id')->on('drugs');
        });

        Schema::table('internal_supply_request_items', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
            $table->dropForeign(['drug_id']);
            
            $table->unique(['request_id', 'drug_id']);
            
            $table->foreign('request_id')->references('id')->on('internal_supply_requests')->onDelete('cascade');
            $table->foreign('drug_id')->references('id')->on('drugs');
        });
    }
};
