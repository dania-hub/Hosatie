<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDispensingTable extends Migration
{
    public function up()
    {
        Schema::create('dispensing', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('prescription_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('drug_id');
            $table->unsignedBigInteger('pharmacist_id');
            $table->unsignedBigInteger('pharmacy_id');
            $table->date('dispense_month');
            $table->integer('quantity_dispensed');
            $table->boolean('reverted')->default(false);
            $table->dateTime('reverted_at')->nullable();
            $table->unsignedBigInteger('reverted_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['prescription_id', 'drug_id', 'dispense_month']);
            $table->foreign('drug_id')->references('id')->on('drug');
            $table->foreign('prescription_id')->references('id')->on('prescription');
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('pharmacist_id')->references('id')->on('users');
            $table->foreign('pharmacy_id')->references('id')->on('pharmacy');
            $table->foreign('reverted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dispensing');
    }
}
