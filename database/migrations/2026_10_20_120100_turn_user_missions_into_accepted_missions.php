<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * user_missions deixa de ser o sorteio da missão do dia e passa a ser a missão aceita:
     * em andamento sem completed_at, concluída com ele.
     */
    public function up(): void
    {
        // As linhas antigas eram sorteios do dia; nenhuma foi aceita, porque aceitar não existia.
        DB::table('user_missions')->delete();

        Schema::table('user_missions', function (Blueprint $table) {
            $table->dropColumn(['completed', 'assigned_date']);
        });

        Schema::table('user_missions', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('mission_id');
            $table->unique(['user_id', 'mission_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Os sorteios apagados no up() não voltam.
     */
    public function down(): void
    {
        Schema::table('user_missions', function (Blueprint $table) {
            $table->boolean('completed')->default(false)->after('mission_id');
            $table->date('assigned_date')->nullable()->after('completed');
        });

        DB::table('user_missions')->update([
            'completed' => DB::raw('completed_at IS NOT NULL'),
            'assigned_date' => DB::raw('DATE(accepted_at)'),
        ]);

        Schema::table('user_missions', function (Blueprint $table) {
            // No MySQL, o índice único passa a servir a chave estrangeira; ela sai e volta junto.
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'mission_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn('accepted_at');
        });
    }
};
