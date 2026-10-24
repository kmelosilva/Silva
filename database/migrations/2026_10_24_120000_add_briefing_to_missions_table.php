<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * O briefing do submentor ao aceitar a missão, vindo do catálogo. Missão sem briefing
     * continua aceitando normalmente; a de entrada não tem (fala o Agente Silva).
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->text('briefing')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('briefing');
        });
    }
};
