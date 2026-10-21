<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('po_number')->unique();

            $table->string('supplier');

            $table->decimal('total_amount', 10, 2)->default(0);

            $table->enum('status', [
                'Pending',
                'Ordered',
                'Partially Received',
                'Received',
                'Cancelled'
            ])->default('Pending');

            $table->date('order_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};