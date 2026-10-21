<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // is_active already exists in the staffs table.
        // No changes needed.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do nothing because the column already existed.
    }
};