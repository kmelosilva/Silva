<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coordinates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80);
            $table->unsignedSmallInteger('position');
            $table->string('title');
            $table->text('content')->nullable();
            $table->unsignedSmallInteger('xp');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            // A posição não é única: uma coordenada aposentada pode ter a
            // mesma posição da que a substituiu.
            $table->unique(['mission_id', 'slug']);
            $table->index(['mission_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinates');
    }
};
