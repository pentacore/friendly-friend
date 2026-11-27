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
            $table->jsonb('channels')->default('[]');
            $table->string('quotes_channel_id')->nullable();
        });

        Schema::create('quote_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->string('channel_id');
            $table->foreignId('requested_by_discord_user_id')->constrained('discord_users')->cascadeOnDelete();
            $table->string('status');
            $table->string('before_message_id')->nullable();
            $table->unsignedInteger('messages_scanned')->default(0);
            $table->unsignedInteger('quotes_found')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guild_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->string('source');
            $table->text('context')->nullable();
            $table->timestamp('said_at')->nullable();
            $table->string('said_at_precision')->nullable();
            $table->string('source_message_id')->nullable()->unique();
            $table->string('source_channel_id')->nullable();
            $table->string('source_message_url')->nullable();
            $table->foreignId('import_id')->nullable()->constrained('quote_imports')->nullOnDelete();
            $table->foreignId('submitted_by_discord_user_id')->constrained('discord_users')->cascadeOnDelete();
            $table->text('raw_input');
            $table->jsonb('ai_output')->nullable();
            $table->integer('score')->default(0);
            $table->integer('elo')->default(1000);
            $table->unsignedInteger('matchup_count')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('quote_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('speaker_discord_user_id')->nullable()->constrained('discord_users')->nullOnDelete();
            $table->string('speaker_name');
            $table->text('text');
            $table->timestamps();

            $table->unique(['quote_id', 'position']);
        });

        Schema::create('quote_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_discord_user_id')->constrained('discord_users')->cascadeOnDelete();
            $table->jsonb('snapshot');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_revisions');
        Schema::dropIfExists('quote_lines');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('quote_imports');

        Schema::table('guilds', function (Blueprint $table): void {
            $table->dropColumn(['channels', 'quotes_channel_id']);
        });
    }
};
