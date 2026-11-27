<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a draft's private preview is, so it can say "expired" when the draft is pruned.
        Schema::table('quotes', function (Blueprint $table): void {
            $table->text('preview_token')->nullable();
            $table->string('preview_channel_id')->nullable();
            $table->string('preview_message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn(['preview_token', 'preview_channel_id', 'preview_message_id']);
        });
    }
};
