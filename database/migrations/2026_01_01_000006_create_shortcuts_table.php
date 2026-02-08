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
        Schema::create('shortcuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('keyword');
            $table->string('emoji')->nullable();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['income', 'expense']);
            $table->timestamps();

            $table->unique(['user_id', 'keyword']);
            $table->index('keyword');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shortcuts');
    }
};
