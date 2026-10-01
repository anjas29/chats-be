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
            $table->string('role', 16)->default('user')->after('password');
            $table->boolean('is_banned')->default(false)->after('role');
            $table->string('avatar_path')->nullable()->after('is_banned');
            $table->timestamp('last_seen_at')->nullable()->after('avatar_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_banned', 'avatar_path', 'last_seen_at']);
        });
    }
};
