<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->string('channel_id');
            $table->timestampTz('hour');
            $table->unsignedInteger('count')->default(0);

            $table->unique(['guild_id', 'discord_user_id', 'channel_id', 'hour']);
            $table->index(['guild_id', 'hour']);
        });

        Schema::create('stat_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reactor_discord_user_id')->constrained('discord_users')->cascadeOnDelete();
            $table->foreignId('target_discord_user_id')->nullable()->constrained('discord_users')->nullOnDelete();
            $table->string('emoji');
            $table->date('day');
            $table->unsignedInteger('count')->default(0);

            $table->unique(['guild_id', 'reactor_discord_user_id', 'target_discord_user_id', 'emoji', 'day'])->nullsNotDistinct();
            $table->index(['guild_id', 'day']);
        });

        Schema::create('stat_voice_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->string('channel_id');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();

            $table->index(['guild_id', 'started_at']);
        });

        Schema::create('stat_activity_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->string('activity_name');
            $table->unsignedSmallInteger('activity_type');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();

            $table->index(['guild_id', 'started_at']);
        });

        // At most one open session per member and server (voice) or per game.
        DB::statement('create unique index stat_voice_sessions_open_unique on stat_voice_sessions (guild_id, discord_user_id) where ended_at is null');
        DB::statement('create unique index stat_activity_sessions_open_unique on stat_activity_sessions (guild_id, discord_user_id, activity_name) where ended_at is null');

        Schema::create('stat_daily_voice', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('seconds');

            $table->unique(['guild_id', 'discord_user_id', 'day']);
            $table->index(['guild_id', 'day']);
        });

        Schema::create('stat_daily_activity', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->string('activity_name');
            $table->date('day');
            $table->unsignedInteger('seconds');

            $table->unique(['guild_id', 'discord_user_id', 'activity_name', 'day']);
            $table->index(['guild_id', 'day']);
        });

        Schema::create('stat_daily_voice_pairs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('first_discord_user_id')->constrained('discord_users')->cascadeOnDelete();
            $table->foreignId('second_discord_user_id')->constrained('discord_users')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('seconds');

            $table->unique(['guild_id', 'first_discord_user_id', 'second_discord_user_id', 'day']);
            $table->index(['guild_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_daily_voice_pairs');
        Schema::dropIfExists('stat_daily_activity');
        Schema::dropIfExists('stat_daily_voice');
        Schema::dropIfExists('stat_activity_sessions');
        Schema::dropIfExists('stat_voice_sessions');
        Schema::dropIfExists('stat_reactions');
        Schema::dropIfExists('stat_messages');
    }
};
