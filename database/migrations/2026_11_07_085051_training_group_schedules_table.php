<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_group_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['training_group_id', 'day_of_week', 'start_time'], 'tg_schedules_unique_slot');
            $table->index(['club_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_group_schedules');
    }
};
