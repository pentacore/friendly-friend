<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_rides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_discord_user_id')->constrained('discord_users')->cascadeOnDelete();
            $table->string('direction');
            $table->string('departs_from')->nullable();
            $table->timestampTz('departs_at')->nullable();
            $table->unsignedSmallInteger('seats');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('event_ride_passengers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_ride_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->string('pickup_note')->nullable();
            $table->timestamps();

            $table->unique(['event_ride_id', 'discord_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_ride_passengers');
        Schema::dropIfExists('event_rides');
    }
};
