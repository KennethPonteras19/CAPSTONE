<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('sales')
            ->where('payment_status', 'Paid')
            ->update([
                'paid_amount' => DB::raw('total_amount')
            ]);
    }

    public function down(): void
    {
        DB::table('sales')
            ->where('payment_status', 'Paid')
            ->update([
                'paid_amount' => 0
            ]);
    }
};
