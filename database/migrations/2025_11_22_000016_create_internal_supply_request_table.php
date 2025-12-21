<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalSupplyRequestTable extends Migration
{
    public function up()
    {
        Schema::create('internal_supply_request', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pharmacy_id'); // ✅ العمود يبقى، بدون FK مباشر
            $table->unsignedBigInteger('requested_by');
            $table->enum('status', ['pending', 'approved', 'rejected', 'fulfilled', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Foreign Keys - بدون pharmacy_id
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            
            // Index للأداء
            $table->index('pharmacy_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('internal_supply_request');
    }
}
