<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('superadmin', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            $table->string('username')->unique(); // Used for logging in
            $table->string('email')->unique();    // Optional, but good for recovery
            $table->string('password');           // Will store the hashed password
            $table->rememberToken();              // Required for "Remember Me" features
            $table->timestamps();                 // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('superadmin');
    }
};