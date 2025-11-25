<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComplaintTable extends Migration
{
    public function up()
    {
        Schema::create('complaint', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('patient_id');
            $table->text('message');
            $table->enum('status', ['قيد المراجعة', 'تمت المراجعة'])->default('قيد المراجعة');
            $table->unsignedBigInteger('replied_by')->nullable();
            $table->text('reply_message')->nullable();
            $table->dateTime('replied_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('replied_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('complaint');
    }
}
