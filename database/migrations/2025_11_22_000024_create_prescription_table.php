<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrescriptionTable extends Migration
{
    public function up()
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('hospital_id'); // ✅ يبقى، بدون FK مباشر
            $table->enum('status', ['active', 'cancelled', 'suspended'])->default('active');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->dateTime('cancelled_at')->nullable();
           
             $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Foreign Keys - بدون hospital_id
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('doctor_id')->references('id')->on('users');
          
            
            // Index للأداء
            $table->index('hospital_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('prescriptions');
    }
}
