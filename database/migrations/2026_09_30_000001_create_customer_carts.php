<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('items');
            $table->unsignedInteger('version')->default(1);
            $table->uuid('checkout_key')->unique();
            $table->timestamps();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('scheduled_at'));
        Schema::dropIfExists('customer_carts');
    }
};
