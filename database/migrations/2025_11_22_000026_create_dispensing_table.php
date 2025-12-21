<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateDispensingTable extends Migration
{
    public function up()
    {
        Schema::create('dispensing', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('prescription_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('drug_id');
            $table->unsignedBigInteger('pharmacist_id');
            $table->unsignedBigInteger('pharmacy_id'); // ✅ العمود يبقى، لكن بدون FK مباشر
            $table->date('dispense_month');
            $table->integer('quantity_dispensed');
            $table->boolean('reverted')->default(false);
            $table->dateTime('reverted_at')->nullable();
            $table->unsignedBigInteger('reverted_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Unique constraint
            $table->unique(['prescription_id', 'drug_id', 'dispense_month']);
            
            // Foreign Keys - بدون pharmacy_id
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('pharmacist_id')->references('id')->on('users');
            $table->foreign('reverted_by')->references('id')->on('users')->onDelete('set null');
            
            // Index للأداء
            $table->index('pharmacy_id');
        });

        // Composite Foreign Key - العلاقة مع prescription_drug
        DB::statement('
            ALTER TABLE dispensing
            ADD CONSTRAINT fk_dispensing_prescription_drug
            FOREIGN KEY (prescription_id, drug_id)
            REFERENCES prescription_drug(prescription_id, drug_id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ');
    }

    public function down()
    {
        DB::statement('
            ALTER TABLE dispensing
            DROP FOREIGN KEY fk_dispensing_prescription_drug
        ');
        
        Schema::dropIfExists('dispensing');
    }
}
