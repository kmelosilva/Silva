<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Uma linha por chamada à IA, com os tokens, o custo e o resultado, e nunca o texto.
     * O teto do mês é a soma de cost_usd no mês de Brasília.
     */
    public function up(): void
    {
        Schema::create('ai_calls', function (Blueprint $table) {
            $table->id();
            // Nulo nas rodadas de avaliação e nas contas apagadas.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->decimal('cost_usd', 10, 6);
            // reply, care, blocked, refusal, invalid, timeout ou error.
            $table->string('outcome', 16);
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
    }
};
