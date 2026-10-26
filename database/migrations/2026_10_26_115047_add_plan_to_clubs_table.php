<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('suspended_at')
                ->constrained('plans')->restrictOnDelete();
            $table->timestamp('subscription_starts_at')->nullable()->after('plan_id');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_starts_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropIndex(['subscription_ends_at']);
            $table->dropColumn(['subscription_starts_at', 'subscription_ends_at']);
        });
    }
};
