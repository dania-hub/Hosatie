<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExternalSupplyRequestTable extends Migration
{
    public function up()
    {
        Schema::create('external_supply_request', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('hospital_id');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('requested_by');
             $table->unsignedBigInteger('approved_by')->nullable();
            $table->enum('status', ['pending', 'approved', 'fulfilled', 'rejected'])->default('pending');
            $table->timestamps();

            $table->foreign('hospital_id')->references('id')->on('hospital');
            $table->foreign('supplier_id')->references('id')->on('supplier');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('external_supply_request');
    }
}
