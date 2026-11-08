<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_subscription_id')->constrained('player_subscriptions')->restrictOnDelete();

            // Per-club receipt number, allocated by NextDocumentNumberAction.
            $table->unsignedInteger('receipt_number');
            $table->unsignedInteger('amount_cents');
            $table->string('method', 20);
            $table->date('paid_on');
            $table->string('reference', 100)->nullable();
            $table->string('note', 255)->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            // Payments are immutable: a paid receipt is voided, never deleted.
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();

            $table->timestamps();

            $table->unique(['club_id', 'receipt_number']);
            $table->index(['club_id', 'player_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
