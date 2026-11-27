<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\QuoteNormalizerAgent;
use App\Enums\SaidAtPrecision;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\NormalizedQuote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Normalizes quotes with an AI (laravel/ai, provider failover across the
 * configured providers that have keys) and falls back to plain parsing when
 * no provider is configured, the AI fails or answers nonsense.
 *
 * What is certain is never left to the AI: a known speaker (message author)
 * and the exact message time always win over its answer.
 */
final readonly class AiQuoteNormalizer implements QuoteNormalizer
{
    public function __construct(private PlainQuoteNormalizer $plain) {}

    public function normalize(CaptureInput $input): NormalizedQuote
    {
        $providers = $this->providers();

        if ($providers === []) {
            return $this->plain->normalize($input);
        }

        try {
            $answer = new QuoteNormalizerAgent()->prompt(
                $this->prompt($input),
                provider: $providers,
                timeout: config()->integer('quotes.ai.timeout'),
            );

            return $this->fromAnswer($input, $answer->toArray()) ?? $this->plain->normalize($input);
        } catch (Throwable $e) {
            Log::warning('AI quote normalization failed; using plain parsing.', ['exception' => $e->getMessage()]);

            return $this->plain->normalize($input);
        }
    }

    /**
     * Configured providers that have an API key, in order.
     *
     * @return list<string>
     */
    public function providers(): array
    {
        $configured = array_filter(config()->array('quotes.ai.providers'), is_string(...));

        return array_values(array_filter(
            $configured,
            fn (string $provider): bool => is_string(config("ai.providers.{$provider}.key")) && config("ai.providers.{$provider}.key") !== '',
        ));
    }

    private function prompt(CaptureInput $input): string
    {
        $parts = ['Current date: '.$input->now->toDateString()];

        if ($input->knownSpeaker instanceof NormalizedLine) {
            $parts[] = "Known speaker (said all of it): {$input->knownSpeaker->speakerName}";
        } elseif ($input->speakerHint instanceof NormalizedLine) {
            $parts[] = "Said by (unless the text is clearly a dialogue): {$input->speakerHint->speakerName}";
        }

        if ($input->context !== null && $input->context !== '') {
            $parts[] = "Context given: {$input->context}";
        }

        if ($input->when !== null && $input->when !== '') {
            $parts[] = "When, as given: {$input->when}";
        }

        $parts[] = "Input:\n".$input->readableText();

        return implode("\n", $parts);
    }

    /**
     * @param  array<mixed>  $answer
     */
    private function fromAnswer(CaptureInput $input, array $answer): ?NormalizedQuote
    {
        $lines = $this->lines($input, $answer['lines'] ?? null);

        if ($lines === []) {
            return null;
        }

        [$saidAt, $precision] = $this->time($input, $answer);
        $context = is_string($answer['context'] ?? null) && trim($answer['context']) !== '' ? trim($answer['context']) : $input->context;

        return new NormalizedQuote($lines, $context, $saidAt, $precision, $answer);
    }

    /**
     * @return list<NormalizedLine>
     */
    private function lines(CaptureInput $input, mixed $rawLines): array
    {
        if (! is_array($rawLines)) {
            return [];
        }

        $known = $input->knownSpeaker;
        // Discord IDs look numeric, so PHP turns them into int array keys:
        // build the name → ID map explicitly to keep them strings.
        $byName = [];

        foreach ($input->mentionNames as $discordId => $name) {
            $byName[mb_strtolower($name)] = (string) $discordId;
        }

        $lines = [];

        foreach ($rawLines as $raw) {
            $speaker = is_array($raw) && is_string($raw['speaker'] ?? null) ? trim(ltrim($raw['speaker'], '@')) : '';
            $text = is_array($raw) && is_string($raw['text'] ?? null) ? trim($raw['text']) : '';

            if ($text === '') {
                continue;
            }

            $lines[] = $known instanceof NormalizedLine
                ? new NormalizedLine($known->speakerName, $text, $known->speakerDiscordId)
                : new NormalizedLine($speaker !== '' ? $speaker : 'Unknown', $text, $byName[mb_strtolower($speaker)] ?? null);
        }

        // A message author said one thing: join whatever the AI split.
        if ($known instanceof NormalizedLine && count($lines) > 1) {
            $lines = [new NormalizedLine($known->speakerName, implode("\n", array_map(fn (NormalizedLine $line): string => $line->text, $lines)), $known->speakerDiscordId)];
        }

        return $lines;
    }

    /**
     * @param  array<mixed>  $answer
     * @return array{0: CarbonImmutable|null, 1: SaidAtPrecision|null}
     */
    private function time(CaptureInput $input, array $answer): array
    {
        if ($input->messageTime instanceof CarbonImmutable) {
            return [$input->messageTime, SaidAtPrecision::Exact];
        }

        $date = $answer['said_at'] ?? null;
        $precision = is_string($answer['said_at_precision'] ?? null) ? SaidAtPrecision::tryFrom($answer['said_at_precision']) : null;

        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}/', $date) !== 1) {
            return [null, null];
        }

        try {
            return [CarbonImmutable::parse(substr($date, 0, 10), $input->now->getTimezone())->startOfDay(), $precision ?? SaidAtPrecision::Day];
        } catch (Throwable) {
            return [null, null];
        }
    }
}
