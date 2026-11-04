<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('national_id', 14)->nullable();
            $table->date('birth_date');
            $table->string('gender', 10);
            $table->string('phone', 20)->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'national_id']);
            $table->index(['club_id', 'name']);
            $table->index(['club_id', 'birth_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
