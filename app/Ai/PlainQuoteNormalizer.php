<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\SaidAtPrecision;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\NormalizedQuote;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Deterministic parsing without AI: the fallback when no AI provider is
 * configured or the AI fails. Understands `"text" - Person`, "Name: text"
 * dialogue lines and `Name said "text" …`, plus simple dates ("yesterday",
 * 2026-10-31).
 */
final readonly class PlainQuoteNormalizer implements QuoteNormalizer
{
    private const string UNKNOWN_SPEAKER = 'Unknown';

    public function normalize(CaptureInput $input): NormalizedQuote
    {
        $text = $input->readableText();
        $context = [$input->context];

        if ($input->knownSpeaker instanceof NormalizedLine) {
            $lines = [$this->speakerLine($input->knownSpeaker, $text)];
        } elseif ($input->speakerHint instanceof NormalizedLine) {
            $lines = $this->dialogue($text) ?? [$this->speakerLine($input->speakerHint, $text)];
        } else {
            [$lines, $rest] = $this->parseFreeText($text);
            $context[] = $rest;
        }

        [$saidAt, $precision, $vagueWhen] = $this->when($input);
        $context[] = $vagueWhen;

        return new NormalizedQuote(
            lines: $lines,
            context: $this->joinContext($context),
            saidAt: $saidAt,
            saidAtPrecision: $precision,
        );
    }

    /**
     * When it was said: the message time is exact; "today"/"yesterday" and
     * ISO dates are a day; anything else stays words in the context.
     *
     * @return array{0: CarbonImmutable|null, 1: SaidAtPrecision|null, 2: string|null}
     */
    public function when(CaptureInput $input): array
    {
        if ($input->messageTime instanceof CarbonImmutable) {
            return [$input->messageTime, SaidAtPrecision::Exact, null];
        }

        $when = trim((string) $input->when);

        if ($when === '') {
            return [null, null, null];
        }

        $day = match (mb_strtolower($when)) {
            'today', 'idag', 'i dag' => $input->now,
            'yesterday', 'igår', 'i går' => $input->now->subDay(),
            default => $this->isoDate($when, $input),
        };

        return $day instanceof CarbonImmutable
            ? [$day->startOfDay(), SaidAtPrecision::Day, null]
            : [null, null, $when];
    }

    private function isoDate(string $when, CaptureInput $input): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $when) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $when, $input->now->getTimezone()) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function speakerLine(NormalizedLine $speaker, string $text): NormalizedLine
    {
        return new NormalizedLine($speaker->speakerName, $text, $speaker->speakerDiscordId);
    }

    /**
     * @return array{0: list<NormalizedLine>, 1: string|null}
     */
    private function parseFreeText(string $text): array
    {
        // "text" - Person (checked first: the quote itself may contain a colon).
        if (preg_match('/^["“”](.+)["“”]\s*[-–—]\s*(.{1,60})$/su', $text, $match) === 1) {
            return [[new NormalizedLine(trim($match[2]), trim($match[1]))], null];
        }

        $dialogue = $this->dialogue($text);

        if ($dialogue !== null) {
            return [$dialogue, null];
        }

        if (preg_match('/^(.{1,40}?)\s+(?:said|says|sa)\s*[:,]?\s*["“”\'](.+?)["“”\']\s*(.*)$/isu', $text, $match) === 1) {
            return [[new NormalizedLine(trim($match[1]), trim($match[2]))], trim($match[3]) ?: null];
        }

        return [[new NormalizedLine(self::UNKNOWN_SPEAKER, $text)], null];
    }

    /**
     * "Name: text" on every non-empty line, or null if it isn't a dialogue.
     *
     * @return list<NormalizedLine>|null
     */
    private function dialogue(string $text): ?array
    {
        $rows = array_values(array_filter(array_map(trim(...), explode("\n", $text)), fn (string $row): bool => $row !== ''));
        $lines = [];

        foreach ($rows as $row) {
            if (preg_match('/^([^:]{1,40}):\s*(.+)$/u', $row, $match) !== 1) {
                return null;
            }

            $lines[] = new NormalizedLine(trim($match[1]), trim($match[2]));
        }

        return $lines === [] ? null : $lines;
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function joinContext(array $parts): ?string
    {
        $parts = array_values(array_filter(array_map(fn (?string $part): string => trim((string) $part), $parts)));

        return $parts === [] ? null : implode('; ', $parts);
    }
}
