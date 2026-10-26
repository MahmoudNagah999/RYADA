<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('status')->default('active')->after('slug')->index();
            $table->timestamp('suspended_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'suspended_at']);
        });
    }
};
