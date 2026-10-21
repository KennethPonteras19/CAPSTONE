<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no');
            $table->string('staff_name');
            $table->string('status')->default('Completed');
            $table->string('item_name');
            $table->decimal('total_amount',10,2);
            $table->string('payment_status')->default('Paid');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
};