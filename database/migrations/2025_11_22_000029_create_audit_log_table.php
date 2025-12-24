<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditLogTable extends Migration
{
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('hospital_id')->nullable(); // ✅ يبقى، بدون FK مباشر
            $table->string('action');
            $table->string('table_name', 100)->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Foreign Keys - بدون hospital_id
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            // Index للأداء
            $table->index('hospital_id');
            $table->index(['user_id', 'hospital_id']); // للاستعلامات المركبة
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
}
