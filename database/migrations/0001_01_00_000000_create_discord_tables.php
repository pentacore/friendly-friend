<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guilds', function (Blueprint $table): void {
            $table->id();
            $table->string('discord_id')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->boolean('bot_present')->default(true);
            $table->boolean('enabled')->default(false);
            $table->boolean('allow_all_members')->default(false);
            $table->jsonb('allowed_role_ids')->default('[]');
            $table->jsonb('admin_role_ids')->default('[]');
            $table->jsonb('roles')->default('[]');
            $table->timestamps();
        });

        Schema::create('discord_users', function (Blueprint $table): void {
            $table->id();
            $table->string('discord_id')->unique();
            $table->string('username');
            $table->string('global_name')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamp('tracking_opted_out_at')->nullable();
            $table->timestamps();
        });

        Schema::create('guild_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->string('nick')->nullable();
            $table->string('guild_avatar')->nullable();
            $table->jsonb('role_ids')->default('[]');
            $table->boolean('in_guild')->default(true);
            $table->timestamps();

            $table->unique(['guild_id', 'discord_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guild_members');
        Schema::dropIfExists('discord_users');
        Schema::dropIfExists('guilds');
    }
};
