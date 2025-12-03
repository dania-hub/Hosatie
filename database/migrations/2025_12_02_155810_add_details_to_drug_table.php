<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('drug', function (Blueprint $table) {
        $table->text('indications')->nullable()->after('warnings'); // دواعي الاستعمال
        $table->text('contraindications')->nullable()->after('indications'); // موانع الاستعمال
    });
}

public function down()
{
    Schema::table('drug', function (Blueprint $table) {
        $table->dropColumn(['indications', 'contraindications']);
    });
}

};
