<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->timestampTz('rsvp_by')->nullable();
            $table->timestampTz('reminded_rsvp_at')->nullable();
            $table->timestampTz('rsvp_closed_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['rsvp_by', 'reminded_rsvp_at', 'rsvp_closed_synced_at']);
        });
    }
};
