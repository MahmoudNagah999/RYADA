<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('club_id')
                ->nullable()
                ->after('causer_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['club_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['club_id', 'created_at']);
            $table->dropConstrainedForeignId('club_id');
        });
    }
};
