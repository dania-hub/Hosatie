<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientTransferRequestTable extends Migration
{
    public function up()
    {
        Schema::create('patient_transfer_request', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('from_hospital_id'); // ✅ بدون FK مباشر
            $table->unsignedBigInteger('to_hospital_id'); // ✅ يبقى FK لأنه user input
            $table->unsignedBigInteger('requested_by');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('to_hospital_id')->references('id')->on('hospital'); // ✅ يبقى
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
            
            // Index للأداء
            $table->index('from_hospital_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('patient_transfer_request');
    }
}
