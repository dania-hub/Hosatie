<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1️⃣ احذف أي FK موجود بأي اسم
        DB::statement('ALTER TABLE prescription DROP FOREIGN KEY IF EXISTS prescription_patient_id_foreign');
        DB::statement('ALTER TABLE prescription DROP FOREIGN KEY IF EXISTS prescription_patient_id_foreign_xxx');
        
        // 2️⃣ نظّف البيانات - احتفظ بآخر وصفة لكل مريض
        DB::statement("
            DELETE p1 FROM prescription p1
            INNER JOIN prescription p2 
            WHERE p1.patient_id = p2.patient_id 
            AND p1.id > p2.id
        ");
        
        // 3️⃣ أضف UNIQUE constraint
        DB::statement('ALTER TABLE prescription ADD UNIQUE KEY patient_id_unique (patient_id)');
        
        // 4️⃣ أضف FK جديد مع UNIQUE
        DB::statement('ALTER TABLE prescription ADD CONSTRAINT prescription_patient_id_unique_foreign FOREIGN KEY (patient_id) REFERENCES users(id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE prescription DROP FOREIGN KEY IF EXISTS prescription_patient_id_unique_foreign');
        DB::statement('ALTER TABLE prescription DROP INDEX patient_id_unique');
    }
};
