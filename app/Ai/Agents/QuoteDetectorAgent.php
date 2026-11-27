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
 * Yes/no: is a channel message a saved quote? LLM fallback for imports when
 * Jev (TypeSafe) isn't configured.
 */
#[UseCheapestModel]
#[Temperature(0)]
final class QuoteDetectorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'You look at messages from a Discord channel where friends save funny quotes. Answer whether the message records something someone said (a quote, e.g. "Martin: ..." or "\"...\" - Bo"), as opposed to normal chat, reactions or links.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'is_quote' => $schema->boolean()->required(),
        ];
    }
}
