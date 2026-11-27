<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord_users', function (Blueprint $table): void {
            $table->string('dietary_notes', 300)->nullable();
        });

        Schema::create('event_bring_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('quantity_needed')->default(1);
            $table->timestamps();
        });

        Schema::create('event_bring_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_bring_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['event_bring_item_id', 'discord_user_id']);
        });

        Schema::create('event_shopping_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('quantity')->nullable();
            $table->foreignId('added_by_discord_user_id')->constrained('discord_users')->cascadeOnDelete();
            $table->foreignId('checked_by_discord_user_id')->nullable()->constrained('discord_users')->nullOnDelete();
            $table->timestampTz('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_meals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('slot');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('event_meal_cooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();

            $table->unique(['event_meal_id', 'discord_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_meal_cooks');
        Schema::dropIfExists('event_meals');
        Schema::dropIfExists('event_shopping_items');
        Schema::dropIfExists('event_bring_claims');
        Schema::dropIfExists('event_bring_items');

        Schema::table('discord_users', function (Blueprint $table): void {
            $table->dropColumn('dietary_notes');
        });
    }
};
