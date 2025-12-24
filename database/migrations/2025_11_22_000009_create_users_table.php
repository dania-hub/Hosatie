<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('national_id')->unique();
            $table->date('birth_date'); // ✅ تمت إضافته هنا
            $table->enum('type', ['patient','doctor','pharmacist','warehouse_manager','hospital_admin','supplier_admin','super_admin','department_head','data_entry']);
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('full_name');
            $table->string('password');
            $table->string('fcm_token')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('hospital_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->enum('status', ['active', 'inactive', 'pending_activation'])->default('pending_activation');
            $table->unsignedBigInteger('created_by')->nullable();
           $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();


            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('hospital_id')->references('id')->on('hospitals')->onDelete('set null');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('pharmacy_id')->references('id')->on('pharmacies')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
}
