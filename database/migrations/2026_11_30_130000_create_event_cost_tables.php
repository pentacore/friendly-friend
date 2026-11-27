<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paid_by_discord_user_id')->constrained('discord_users');
            $table->foreignId('added_by_discord_user_id')->constrained('discord_users');
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->string('split_mode');
            $table->timestamps();
        });

        Schema::create('event_expense_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained();
            $table->unsignedBigInteger('amount');

            $table->unique(['event_expense_id', 'discord_user_id']);
        });

        Schema::create('event_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_discord_user_id')->constrained('discord_users');
            $table->foreignId('to_discord_user_id')->constrained('discord_users');
            $table->foreignId('recorded_by_discord_user_id')->constrained('discord_users');
            $table->unsignedBigInteger('amount');
            $table->timestampTz('settled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payments');
        Schema::dropIfExists('event_expense_shares');
        Schema::dropIfExists('event_expenses');
    }
};
