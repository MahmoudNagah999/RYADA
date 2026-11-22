<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #33: `GET /players?gender=female` ran `COUNT(*)` through the club/national-id index and
 * filtered gender outside it: 118 ms on 5,000 players. This covering index serves the count
 * in 2.1 ms and also gives the gender filter a narrow access path for the paginated list.
 * First column is club_id (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->index(['club_id', 'gender'], 'players_club_id_gender_index');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex('players_club_id_gender_index');
        });
    }
};
