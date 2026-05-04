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
        Schema::table('users', function (Blueprint $table) {
            $table->string('login')->nullable()->unique()->after('email');
            $table->string('first_name')->nullable()->after('login');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('api_token', 64)->nullable()->unique()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login']);
            $table->dropUnique(['api_token']);
            $table->dropColumn(['login', 'first_name', 'last_name', 'api_token']);
        });
    }
};
