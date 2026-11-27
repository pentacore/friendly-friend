<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rsvps', function (Blueprint $table): void {
            // Set when "Interested" on the native Discord event made this RSVP; any answer on the site or the buttons clears it.
            $table->boolean('from_discord_interest')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('event_rsvps', function (Blueprint $table): void {
            $table->dropColumn('from_discord_interest');
        });
    }
};
