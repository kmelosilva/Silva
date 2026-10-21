<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Coordenada com esforço físico mostra o aviso de cuidado (Coordinate::CARE_NOTICE).
     * As que já existem ficam sem a marca.
     */
    public function up(): void
    {
        Schema::table('coordinates', function (Blueprint $table) {
            $table->boolean('physical_effort')->default(false)->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coordinates', function (Blueprint $table) {
            $table->dropColumn('physical_effort');
        });
    }
};
