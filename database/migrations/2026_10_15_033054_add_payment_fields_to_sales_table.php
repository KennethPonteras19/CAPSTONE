
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'qty')) {
                $table->unsignedInteger('qty')->default(1);
            }

            if (!Schema::hasColumn('sales', 'paid_amount')) {
                $table->decimal('paid_amount', 10, 2)->default(0);
            }

            if (!Schema::hasColumn('sales', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'order_id')) {
                $table->dropColumn('order_id');
            }

            if (Schema::hasColumn('sales', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }

            if (Schema::hasColumn('sales', 'qty')) {
                $table->dropColumn('qty');
            }
        });
    }
};