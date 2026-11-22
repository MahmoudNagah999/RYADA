<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #33: the Filament audit log lists every club's activity, newest first, with no club
 * predicate. The query scanned 23,800 rows and sorted them (249 ms). `activity_log` is a
 * platform-wide, nullable-club_id audit table read by the super-admin, so this is the one
 * deliberately non-tenant index; `(club_id, created_at)` already serves the club filter.
 * The 20,000-row write probe measured +12.6% insert time, stated in docs/performance.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at', 'activity_log_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_created_at_index');
        });
    }
};
