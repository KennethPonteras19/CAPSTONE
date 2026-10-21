<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->json('permissions')->nullable()->after('is_active');
        });

        Schema::table('staffs', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->json('permissions')->nullable()->after('is_active');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->json('permissions')->nullable()->after('is_active');
        });

        Schema::table('superadmin', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->json('permissions')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'permissions']);
        });

        Schema::table('staffs', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'permissions']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'permissions']);
        });

        Schema::table('superadmin', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'permissions']);
        });
    }
};
