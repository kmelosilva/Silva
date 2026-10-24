<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * As áreas cujo submentor o agente já conheceu, para a apresentação aparecer uma vez
     * por área. Contas que já existem ficam com nulo: não conheceram nenhum.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Chaves de App\Enums\Category, como as prioridades.
            $table->json('submentors_met')->nullable()->after('priorities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('submentors_met');
        });
    }
};
