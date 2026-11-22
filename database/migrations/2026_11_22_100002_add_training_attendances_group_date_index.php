<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #33: the attendance sheet filters `club_id`, `training_group_id` and `date`. The existing
 * unique key ends with `date` after `player_id`, so the sheet scanned every attendance row
 * of the group (1,680 rows for 40 members, 1.5 ms). This index narrows it to 40 rows
 * (0.20 ms). The 20,000-row write probe showed no measurable insert cost (within noise).
 * First column is club_id (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_attendances', function (Blueprint $table) {
            $table->index(
                ['club_id', 'training_group_id', 'date'],
                'training_attendances_club_id_group_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('training_attendances', function (Blueprint $table) {
            $table->dropIndex('training_attendances_club_id_group_date_index');
        });
    }
};
