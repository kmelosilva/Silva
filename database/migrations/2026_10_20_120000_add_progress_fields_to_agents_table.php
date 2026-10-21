<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->unsignedInteger('streak_current')->default(0)->after('xp');
            $table->unsignedInteger('streak_best')->default(0)->after('streak_current');
            // Último dia ativo, no horário de Brasília (AAAA-MM-DD).
            $table->date('last_active_on')->nullable()->after('streak_best');
            $table->unique('user_id');
        });

        // Toda conta que já existe ganha o seu agente; nível, XP e energia vêm dos defaults da tabela.
        $now = now();

        DB::table('agents')->insertUsing(
            ['user_id', 'created_at', 'updated_at'],
            DB::table('users')
                ->select('id')
                ->selectRaw('? as created_at, ? as updated_at', [$now, $now])
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('agents')
                    ->whereColumn('agents.user_id', 'users.id'))
        );
    }

    /**
     * Reverse the migrations.
     *
     * Os agentes criados no up() ficam: não há como distingui-los dos outros.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // No MySQL, o índice único passa a servir a chave estrangeira; ela sai e volta junto.
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn(['streak_current', 'streak_best', 'last_active_on']);
        });
    }
};
