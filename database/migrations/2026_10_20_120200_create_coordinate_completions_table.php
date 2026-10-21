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
        Schema::create('coordinate_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coordinate_id')->constrained()->cascadeOnDelete();
            // XP creditado no momento; mudar o catálogo depois não mexe no que já foi ganho.
            $table->unsignedSmallInteger('xp');
            $table->timestamp('completed_at');
            $table->timestamps();

            // Cada coordenada conta uma vez por agente, inclusive com clique duplo.
            $table->unique(['user_id', 'coordinate_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coordinate_completions');
    }
};
