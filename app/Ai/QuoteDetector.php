<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\QuoteDetectorAgent;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Throwable;

/**
 * Is a channel message a quote? Used by channel imports to skip normal chat
 * before the more expensive extraction. In order:
 *
 * 1. Jev (through OpenRouter) when OPENROUTER_API_KEY is set: fast, cheap,
 *    calibrated probability.
 * 2. A cheap LLM (QuoteDetectorAgent) when a quote AI provider has a key.
 * 3. Heuristics: "Name: text" lines, quotation marks, "said".
 */
final readonly class QuoteDetector
{
    public function __construct(private AiQuoteNormalizer $normalizer, private Jev $jev) {}

    public function isQuote(string $text): bool
    {
        $text = trim($text);

        if ($text === '' || preg_match('~^https?://\S+$~', $text) === 1) {
            return false;
        }

        try {
            if ($this->jev->isAvailable()) {
                $answer = $this->jev->ask($text, 'quote', new Boolean(
                    'Does this Discord message record something a person said, as a saved quote?',
                    ['true' => 'A quote: "Name: words", "\"words\" - Name", or retelling what someone said.', 'false' => 'Normal chat, reactions, links, questions to the channel.'],
                ));

                return $answer instanceof BooleanAnswer && $answer->isTrue(0.5);
            }

            $providers = $this->normalizer->providers();

            if ($providers !== []) {
                return new QuoteDetectorAgent()->prompt($text, provider: $providers, timeout: config()->integer('quotes.ai.timeout'))['is_quote'] === true;
            }
        } catch (Throwable $e) {
            Log::warning('AI quote detection failed; using heuristics.', ['exception' => $e->getMessage()]);
        }

        return $this->looksLikeQuote($text);
    }

    private function looksLikeQuote(string $text): bool
    {
        return preg_match('/^[^:\n]{1,40}:\s*\S/mu', $text) === 1
            || preg_match('/["“”].{3,}["“”]/u', $text) === 1
            || preg_match('/\b(said|says|sa)\b/iu', $text) === 1;
    }
}
