<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guilds', function (Blueprint $table): void {
            $table->string('events_channel_id')->nullable();
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->string('announce_channel_id')->nullable();
            $table->string('discord_message_id')->nullable();
            $table->string('discord_scheduled_event_id')->nullable();
            $table->timestampTz('reminded_day_at')->nullable();
            $table->timestampTz('reminded_hours_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['announce_channel_id', 'discord_message_id', 'discord_scheduled_event_id', 'reminded_day_at', 'reminded_hours_at']);
        });

        Schema::table('guilds', function (Blueprint $table): void {
            $table->dropColumn('events_channel_id');
        });
    }
};
