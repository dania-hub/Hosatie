<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDrugsTable extends Migration
{
    public function up()
    {
        Schema::create('drugs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('generic_name');
            $table->string('strength');
            $table->string('form');
            $table->string('category');
            $table->string('unit', 50)->default('قرص');
            $table->integer('max_monthly_dose');
            $table->enum('status', ['متوفر', 'غير متوفر', 'تم الصرف'])->default('متوفر');
            $table->string('manufacturer');
            $table->string('country');
            $table->string('utilization_type');
            $table->text('warnings');
            $table->text('indications'); // ✅ دواعي الاستعمال
            $table->text('contraindications'); // ✅ موانع الاستعمال
            $table->date('expiry_date');
            $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['name','generic_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('drugs');
    }
}
