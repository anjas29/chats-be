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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('name')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('direct_key')->nullable()->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable()->index();
            $table->timestamps();
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
