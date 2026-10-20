<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categorias válidas na data desta migração. A lista viva é App\Enums\Category;
     * aqui ela fica congelada para a migração dar o mesmo resultado no futuro.
     */
    private const VALID_CATEGORIES = ['saude', 'mente', 'financas', 'carreira', 'social', 'habitos'];

    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->string('slug', 80)->nullable()->unique()->after('id');
            $table->boolean('is_entry')->default(false)->after('category');
            $table->unsignedSmallInteger('position')->default(0)->after('is_entry');
            $table->timestamp('retired_at')->nullable()->after('position');
        });

        // O XP passa a morar só na coordenada; o da missão é a soma delas.
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('xp');
        });

        // As missões geradas automaticamente usavam categorias fora da lista
        // (corpo, disciplina). A carga do catálogo as aposenta; aqui elas só
        // deixam de carregar um valor que o enum recusaria.
        DB::table('missions')
            ->whereNotNull('category')
            ->whereNotIn('category', self::VALID_CATEGORIES)
            ->update(['category' => null]);
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->integer('xp')->default(10)->after('description');
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'is_entry', 'position', 'retired_at']);
        });
    }
};
