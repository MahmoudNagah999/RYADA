<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedInteger('last_number')->default(0);

            $table->timestamps();

            $table->unique(['club_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
