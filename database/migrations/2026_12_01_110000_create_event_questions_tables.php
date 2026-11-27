<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('prompt');
            $table->string('kind');
            $table->jsonb('options')->default('[]');
            $table->timestamps();
        });

        Schema::create('event_question_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discord_user_id')->constrained()->cascadeOnDelete();
            $table->string('value', 500);
            $table->timestamps();

            $table->unique(['event_question_id', 'discord_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_question_answers');
        Schema::dropIfExists('event_questions');
    }
};
