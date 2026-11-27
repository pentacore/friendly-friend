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
        Schema::table('quotes', function (Blueprint $table): void {
            $table->text('search_text')->default('');
        });

        // 'simple': no stemming or stop words, which suits mixed Swedish/English quotes.
        DB::statement("ALTER TABLE quotes ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', search_text)) STORED");
        DB::statement('CREATE INDEX quotes_search_vector_index ON quotes USING gin (search_vector)');

        // Backfill quotes saved before search existed (Quote::refreshSearchText keeps it current).
        DB::statement(<<<'SQL'
            UPDATE quotes SET search_text = trim(concat_ws(' ',
                (SELECT string_agg(speaker_name || ' ' || text, ' ' ORDER BY position) FROM quote_lines WHERE quote_lines.quote_id = quotes.id),
                context))
            SQL);

        Schema::create('quote_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('value');
            $table->timestamps();

            $table->unique(['quote_id', 'user_id']);
        });

        Schema::create('quote_matchups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_a_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('quote_b_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('winner_quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->index(['guild_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_matchups');
        Schema::dropIfExists('quote_votes');

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex(['guild_id', 'status', 'published_at']);
            $table->dropColumn(['search_vector', 'search_text']);
        });
    }
};
