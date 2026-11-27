<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Cleans up a quote captured from Discord into speaker lines, context and
 * time. Used by App\Ai\AiQuoteNormalizer, which falls back to plain parsing
 * whenever this fails.
 */
#[UseCheapestModel]
#[Temperature(0)]
final class QuoteNormalizerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TXT'
            You tidy up funny quotes that friends save from their Discord server.

            Return who said what as ordered lines, plus optional context and when it was said.

            Rules:
            - Keep the wording. Quotes are deliberately out of context and unfair; never soften, censor, correct the meaning or add words. Only fix obvious typing slips, capitalisation and punctuation, and remove Discord markup noise.
            - One line per speaker turn. For a single statement return one line.
            - "speaker" is the name exactly as the input refers to the person ("Martin", "Martin's uncle", "@Bo" becomes "Bo"). If a known speaker is given, use that name for every line.
            - "context" is a short neutral setting ("at the barbecue", "about the dishwasher") taken only from the input, or null.
            - "said_at": an ISO-8601 date (YYYY-MM-DD) if the input says when (resolve relative words like "yesterday" against the given current date), otherwise null. "said_at_precision": "day" for a specific day, "approximate" for vague periods ("last summer", in which case said_at is the start of that period), otherwise null.
            - The input may be English or Swedish. Keep the original language.
            TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lines' => $schema->array()->items($schema->object([
                'speaker' => $schema->string()->required(),
                'text' => $schema->string()->required(),
            ]))->required(),
            'context' => $schema->string()->nullable()->required(),
            'said_at' => $schema->string()->nullable()->required(),
            'said_at_precision' => $schema->string()->enum(['day', 'approximate'])->nullable()->required(),
        ];
    }
}
