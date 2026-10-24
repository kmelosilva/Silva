<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A reflexão do agente sobre uma coordenada cumprida, com a resposta do submentor.
     * É só do agente, e apagar apaga a linha de verdade.
     *
     * A marca de "a IA já respondeu" fica na conclusão, que nunca é apagada: apagar a
     * reflexão e escrever de novo não gera uma segunda resposta por IA.
     */
    public function up(): void
    {
        Schema::create('reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Uma reflexão por coordenada cumprida.
            $table->foreignId('coordinate_completion_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('text', 500);
            $table->text('reply');
            // ai, written ou care.
            $table->string('reply_source', 10);
            $table->timestamps();
        });

        Schema::table('coordinate_completions', function (Blueprint $table) {
            $table->timestamp('ai_replied_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Apaga as reflexões guardadas.
     */
    public function down(): void
    {
        Schema::table('coordinate_completions', function (Blueprint $table) {
            $table->dropColumn('ai_replied_at');
        });

        Schema::dropIfExists('reflections');
    }
};
