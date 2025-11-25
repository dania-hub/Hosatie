<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePharmaciesTable extends Migration
{
    public function up()
    {
        Schema::create('pharmacy', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('hospital_id');
             $table->unsignedBigInteger('inventory_id')->nullable();
            $table->string('name');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['hospital_id', 'name']);
            $table->foreign('hospital_id')->references('id')->on('hospital')->onDelete('cascade');
            $table->foreign('inventory_id')->references('id')->on('inventory')->onDelete('set null')->onUpdate('cascade');});
    }

    public function down()
    {
        Schema::dropIfExists('pharmacy');
    }
}
