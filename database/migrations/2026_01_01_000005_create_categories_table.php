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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('emoji');
            $table->enum('type', ['income', 'expense']);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
