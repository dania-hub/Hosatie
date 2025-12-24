<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehousesTable extends Migration
{
    public function up()
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger(column: 'hospital_id');
            $table->string('name')->default('المخزن الرئيسي');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();


            $table->unique('hospital_id');
            $table->foreign('hospital_id')->references('id')->on('hospitals')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouses');
    }
}
