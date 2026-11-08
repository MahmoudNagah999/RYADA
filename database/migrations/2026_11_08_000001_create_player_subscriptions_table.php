<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();

            // ends_on is exclusive: the subscription is valid while today < ends_on.
            $table->date('starts_on');
            $table->date('ends_on');

            // Snapshot of the plan at subscription time.
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3);
            $table->unsignedTinyInteger('duration_months');

            $table->string('discount_type', 10)->nullable();
            $table->unsignedInteger('discount_value')->nullable();
            $table->unsignedInteger('discount_cents')->default(0);

            $table->unsignedInteger('total_cents');

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('renewed_from_id')->nullable()->constrained('player_subscriptions')->nullOnDelete();

            $table->timestamps();

            $table->index(['club_id', 'player_id']);
            $table->index(['club_id', 'membership_plan_id']);
            $table->index(['club_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_subscriptions');
    }
};
