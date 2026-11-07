<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_group_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_group_id', 'player_id']);
            $table->index(['club_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_group_player');
    }
};
