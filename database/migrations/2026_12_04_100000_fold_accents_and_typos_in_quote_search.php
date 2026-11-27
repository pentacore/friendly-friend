<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quote search ignores accents ("hakan" finds "Håkan") and can fall back to
 * close matches for typos ("kanelbule"). `unaccent()` is only STABLE, so an
 * IMMUTABLE wrapper with the dictionary named is what generated columns and
 * expression indexes accept.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION f_unaccent(text) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
            AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$
            SQL);

        DB::statement('DROP INDEX quotes_search_vector_index');
        DB::statement('ALTER TABLE quotes DROP COLUMN search_vector');
        DB::statement("ALTER TABLE quotes ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', f_unaccent(search_text))) STORED");
        DB::statement('CREATE INDEX quotes_search_vector_index ON quotes USING gin (search_vector)');
        DB::statement('CREATE INDEX quotes_search_text_trgm ON quotes USING gin (f_unaccent(lower(search_text)) gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX quotes_search_text_trgm');
        DB::statement('DROP INDEX quotes_search_vector_index');
        DB::statement('ALTER TABLE quotes DROP COLUMN search_vector');
        DB::statement("ALTER TABLE quotes ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', search_text)) STORED");
        DB::statement('CREATE INDEX quotes_search_vector_index ON quotes USING gin (search_vector)');
        DB::statement('DROP FUNCTION IF EXISTS f_unaccent(text)');
        DB::statement('DROP EXTENSION IF EXISTS unaccent');
    }
};
