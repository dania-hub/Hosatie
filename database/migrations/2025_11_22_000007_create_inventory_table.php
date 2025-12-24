<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryTable extends Migration
{
    public function up()
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('drug_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->integer('current_quantity')->default(0);
            $table->integer('minimum_level')->default(50);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();


          
            $table->foreign('drug_id')->references('id')->on('drugs');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventories');
    }
}
