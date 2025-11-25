<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDrugsTable extends Migration
{
    public function up()
    {
        Schema::create('drug', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('strength')->nullable();
            $table->string('form')->nullable();
            $table->string('category')->nullable();
            $table->string('unit', 50)->default('قرص');
            $table->integer('max_monthly_dose')->nullable();
            $table->enum('status', ['متوفر', 'غير متوفر', 'تم الصرف'])->default('متوفر');
            $table->string('manufacturer')->nullable();
            $table->string('country')->nullable();
            $table->string('utilization_type')->nullable();
            $table->text('warnings')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->unique(['name', 'strength']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('drug');
    }
}
