<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_discord_user_id')->constrained('discord_users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('location_url')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone');
            $table->string('status')->default('planned');
            $table->string('currency', 3)->default('SEK');
            $table->timestamps();

            $table->index(['guild_id', 'starts_at']);
        });

        Schema::create('event_hosts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();

            $table->unique(['event_id', 'discord_user_id']);
        });

        Schema::create('event_rsvps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->unsignedSmallInteger('guests')->default(0);
            $table->string('note')->nullable();
            $table->boolean('needs_ride')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'discord_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rsvps');
        Schema::dropIfExists('event_hosts');
        Schema::dropIfExists('events');
    }
};
