<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();

            // Per-club refund number, allocated by NextDocumentNumberAction.
            $table->unsignedInteger('refund_number');
            $table->unsignedInteger('amount_cents');
            $table->string('method', 20);
            $table->date('refunded_on');
            $table->string('reason', 255);

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['club_id', 'refund_number']);
            $table->index(['club_id', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
