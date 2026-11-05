<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_sport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'sport_id']);
            $table->index('sport_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_sport');
    }
};
