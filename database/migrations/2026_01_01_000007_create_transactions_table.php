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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('group_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->enum('type', ['income', 'expense']);
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->enum('source', ['line', 'web'])->default('line');
            $table->string('line_message_id')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type']);
            $table->index(['group_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
