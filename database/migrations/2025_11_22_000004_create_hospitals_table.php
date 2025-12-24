<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHospitalsTable extends Migration
{
    public function up()
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string(column: 'name');
            $table->string('code')->unique();
            $table->enum('type', ['hospital', 'health_center', 'clinic'])->default('hospital');
            $table->enum('city', ['طرابلس', 'بنغازي'])->default('طرابلس');
            $table->text('address')->nullable();
            $table->string('phone', 20);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();


            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null')->onUpdate('cascade');
            
        });
    }

    public function down()
    {
        Schema::dropIfExists('hospitals');
    }
}
