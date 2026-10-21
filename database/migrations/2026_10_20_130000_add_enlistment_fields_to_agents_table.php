<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * O alistamento mora no agente; o passo atual é calculado a partir destes campos
     * (App\Enums\EnlistmentStep). Contas que já existem ficam com tudo nulo: começam no juramento.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('codename', 60)->nullable()->after('user_id');
            // Chaves de App\Enums\Category, até 2. Nulo ou vazio: sem prioridade.
            $table->json('priorities')->nullable()->after('codename');
            $table->timestamp('oath_accepted_at')->nullable()->after('priorities');
            $table->timestamp('enlisted_at')->nullable()->after('oath_accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['codename', 'priorities', 'oath_accepted_at', 'enlisted_at']);
        });
    }
};
