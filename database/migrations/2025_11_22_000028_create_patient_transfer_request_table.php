<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientTransferRequestTable extends Migration
{
    public function up()
    {
        Schema::create('patient_transfer_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('from_hospital_id'); // ✅ بدون FK مباشر
            $table->unsignedBigInteger('to_hospital_id'); // ✅ يبقى FK لأنه user input
            $table->unsignedBigInteger('requested_by');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason');
            $table->enum('transfer_reason', ['تغير مكان السكن', 'الحاجة إلى تخصص طبي غير متوفر في المستشفى الحالي', 'عدم رضا عن جودة الخدمة الطبية المقدمة', 'صعوبة الوصول إلى المستشفى','غير ذلك'])->default('غير ذلك');
            $table->unsignedBigInteger('handeled_by')->nullable();
            $table->dateTime('handeled_at')->nullable();
           
          
            $table->text('rejection_reason')->nullable();
$table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Foreign Keys
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('to_hospital_id')->references('id')->on('hospitals'); // ✅ يبقى
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('handeled_by')->references('id')->on('users')->onDelete('set null');

            
            // Index للأداء
            $table->index('from_hospital_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('patient_transfer_requests');
    }
}
