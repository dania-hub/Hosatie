<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrescriptionDrugTable extends Migration
{
    public function up()
    {
        Schema::create('prescription_drug', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('prescription_id');
            $table->unsignedBigInteger('drug_id');
            $table->integer('monthly_quantity');
            $table->string('note')->nullable();
            $table->timestamps();

            // Keep the auto-increment `id` as the primary key. Use a unique index to prevent
            // duplicate (prescription_id, drug_id) pairs instead of making them the primary key.
            $table->unique(['prescription_id', 'drug_id']);
            $table->foreign('prescription_id')->references('id')->on('prescription')->onDelete('cascade');
            $table->foreign('drug_id')->references('id')->on('drug');
        });
    }

    public function down()
    {
        Schema::dropIfExists('prescription_drug');
    }
}
