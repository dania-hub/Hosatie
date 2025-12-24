<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalSupplyRequestItemTable extends Migration
{
    public function up()
    {
        Schema::create('internal_supply_request_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('drug_id');
            $table->integer('requested_qty');
            $table->integer('approved_qty')->nullable();
            $table->integer('fulfilled_qty')->nullable();
            $table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();


            // Keep the auto-incrementing `id` as the primary key and enforce uniqueness
            // on (request_id, drug_id) so there can't be duplicate items for the same request
            $table->unique(['request_id', 'drug_id']);
            $table->foreign('request_id')->references('id')->on('internal_supply_requests')->onDelete('cascade');
            $table->foreign('drug_id')->references('id')->on('drugs');
        });
    }

    public function down()
    {
        Schema::dropIfExists('internal_supply_request_items');
    }
}
