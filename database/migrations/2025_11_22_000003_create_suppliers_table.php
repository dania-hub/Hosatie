<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuppliersTable extends Migration
{
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('phone', 20);
            $table->text('address')->nullable();
            $table->enum('city', allowed: ['طرابلس', 'بنغازي'])->default('طرابلس');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

        });
    }

    public function down()
    {
        Schema::dropIfExists('suppliers');
    }
}
