<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('phone')->nullable()->after('username');

            $table->string('role')->required()->after('phone'); // instrumentist|doctor|circulating|admin
            $table->boolean('is_super_admin')->default(false)->after('role');
            $table->boolean('use_pay_scheme')->default(false)->after('is_super_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'phone', 'role', 'is_super_admin', 'use_pay_scheme']);
        });
    }
};
