<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // While true, starts_at holds the earliest candidate until a date is chosen.
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('date_polling')->default(false);
        });

        Schema::create('event_date_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_date_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_date_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->string('answer');
            $table->timestamps();

            $table->unique(['event_date_option_id', 'discord_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_date_votes');
        Schema::dropIfExists('event_date_options');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('date_polling');
        });
    }
};
