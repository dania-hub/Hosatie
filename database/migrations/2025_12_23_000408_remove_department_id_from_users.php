<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1️⃣ احذف Foreign Key
        DB::statement('ALTER TABLE users DROP FOREIGN KEY IF EXISTS users_department_id_foreign');
        
        // 2️⃣ احذف الـ Index
        DB::statement('ALTER TABLE users DROP INDEX IF EXISTS users_department_id_index');
        DB::statement('ALTER TABLE users DROP INDEX IF EXISTS department_id');
        
        // 3️⃣ احذف العمود تماماً
        Schema::table('users', function ($table) {
            $table->dropColumn('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function ($table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('supplier_id');
            $table->foreign('department_id')->references('id')->on('department')->onDelete('set null');
            $table->index('department_id');
        });
    }
};
