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
        Schema::create('mission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained();
            
            $table->enum('type', [
                'daily',
                'weekly',
                'random',
                'level_unlock',
                'event'
            ]);
        
            $table->integer('min_level')->default(1);
            $table->integer('chance')->default(100);
        
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        
            $table->timestamps();
        });        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_rules');
    }
};
