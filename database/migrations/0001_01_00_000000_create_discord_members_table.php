<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_members', function (Blueprint $table): void {
            $table->id();
            $table->string('discord_id')->unique();
            $table->string('username');
            $table->string('global_name')->nullable();
            $table->string('guild_nick')->nullable();
            $table->string('avatar')->nullable();
            $table->string('guild_avatar')->nullable();
            $table->jsonb('role_ids')->default('[]');
            $table->boolean('in_guild')->default(true);
            $table->timestamp('tracking_opted_out_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_members');
    }
};
