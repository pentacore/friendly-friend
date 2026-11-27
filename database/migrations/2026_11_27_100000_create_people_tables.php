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
        // Fuzzy name matching for ResolvePerson (similarity()).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->foreignId('discord_user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('person_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('alias');
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX person_aliases_alias_lower_unique ON person_aliases (lower(alias))');
        DB::statement('CREATE INDEX people_display_name_trgm ON people USING gin (lower(display_name) gin_trgm_ops)');
        DB::statement('CREATE INDEX person_aliases_alias_trgm ON person_aliases USING gin (lower(alias) gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('person_aliases');
        Schema::dropIfExists('people');
    }
};
