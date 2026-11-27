<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Models\DiscordUser;
use App\Models\Quote;
use App\Quotes\Data\NormalizedQuote;
use Illuminate\Support\Facades\DB;

/**
 * Edits a saved quote: new lines, context and time; every speaker gets a
 * Person, and a revision records the new version.
 */
final readonly class EditQuote
{
    public function __construct(
        private WriteDraftLines $writeLines,
        private EnsureLinePeople $ensurePeople,
        private RecordRevision $recordRevision,
    ) {}

    public function handle(Quote $quote, NormalizedQuote $content, DiscordUser $editor): Quote
    {
        return DB::transaction(function () use ($quote, $content, $editor): Quote {
            $this->writeLines->handle($quote, $content);
            $this->ensurePeople->handle($quote);
            $this->recordRevision->handle($quote, $editor);

            return $quote;
        });
    }
}
