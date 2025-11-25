<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryTable extends Migration
{
    public function up()
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('drug_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->integer('current_quantity')->default(0);
            $table->integer('minimum_level')->default(50);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'drug_id']);
            $table->foreign('drug_id')->references('id')->on('drug');
            $table->foreign('warehouse_id')->references('id')->on('warehouse')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('supplier');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory');
    }
}
