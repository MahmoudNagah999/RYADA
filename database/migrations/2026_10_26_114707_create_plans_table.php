<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->unsignedInteger('max_players')->nullable();
            $table->unsignedInteger('max_coaches')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('EGP');
            $table->unsignedSmallInteger('duration_months');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
