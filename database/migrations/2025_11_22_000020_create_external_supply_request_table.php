<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExternalSupplyRequestTable extends Migration
{
    public function up()
    {
        Schema::create('external_supply_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('hospital_id'); // ✅ يبقى، بدون FK مباشر
            $table->unsignedBigInteger('supplier_id')->nullable(); // ✅ يبقى، بدون FK مباشر
            $table->unsignedBigInteger('requested_by');
           
            $table->enum('status', ['pending', 'approved', 'fulfilled', 'rejected'])->default('pending');
            $table->unsignedBigInteger('handeled_by')->nullable();
            $table->timestamp('handeled_at')->nullable();
           $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();


            // Foreign Keys - فقط للمستخدمين
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('handeled_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes للأداء
            $table->index('hospital_id');
            $table->index('supplier_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('external_supply_requests');
    }
}
