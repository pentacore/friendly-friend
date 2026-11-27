<?php

declare(strict_types=1);

namespace App\Ai;

use App\Quotes\Data\NormalizedLine;
use Carbon\CarbonImmutable;

/**
 * What a QuoteNormalizer gets to work with.
 *
 * - knownSpeaker: the author of a quoted message. The speaker and time are
 *   certain; only the text is tidied.
 * - speakerHint: the `who` of `/quote`. The text is theirs unless it's
 *   clearly a dialogue.
 * - neither: free text ("@bot Martin said …"); everything is extracted.
 */
final readonly class CaptureInput
{
    public CarbonImmutable $now;

    /**
     * @param  array<string, string>  $mentionNames  Discord ID → display name, for <@id> mentions
     */
    public function __construct(
        public string $text,
        public ?NormalizedLine $knownSpeaker = null,
        public ?NormalizedLine $speakerHint = null,
        public array $mentionNames = [],
        public ?string $context = null,
        public ?string $when = null,
        public ?CarbonImmutable $messageTime = null,
        ?CarbonImmutable $now = null,
    ) {
        $this->now = $now ?? CarbonImmutable::now(config()->string('quotes.timezone'));
    }

    /**
     * The text with Discord mentions replaced by "@Name".
     */
    public function readableText(): string
    {
        $text = (string) preg_replace_callback(
            '/<@!?(\d+)>/',
            fn (array $match): string => '@'.($this->mentionNames[$match[1]] ?? 'someone'),
            $this->text,
        );

        return trim($text);
    }
}
