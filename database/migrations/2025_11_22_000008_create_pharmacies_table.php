<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePharmaciesTable extends Migration
{
    public function up()
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('hospital_id');
            
            $table->string('name');
            $table->enum('status', ['active', 'inactive'])->default('active');
           $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();


           
            $table->foreign('hospital_id')->references('id')->on('hospitals')->onDelete('cascade');
});
    }

    public function down()
    {
        Schema::dropIfExists('pharmacies');
    }
}
