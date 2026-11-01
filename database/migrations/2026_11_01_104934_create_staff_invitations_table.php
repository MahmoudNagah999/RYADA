<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token_hash')->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('pending_email')->nullable()->storedAs(
                'CASE WHEN accepted_at IS NULL AND revoked_at IS NULL THEN email ELSE NULL END'
            );
            $table->timestamps();

            $table->index(['club_id', 'email']);
            $table->index(['club_id', 'role']);
            $table->index(['club_id', 'expires_at']);
            $table->unique(['club_id', 'pending_email'], 'staff_invitations_pending_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
    }
};
