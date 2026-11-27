<?php

declare(strict_types=1);

namespace App\Ai;

use Laravel\Ai\Classification;
use Laravel\Ai\Contracts\Question;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\Data\Answer;

/**
 * The one way to ask Jev (TypeSafe's fast, cheap decision model) a question.
 * Every Jev call goes through OpenRouter (laravel/ai's OpenRouter provider,
 * model `~typesafe/jev-latest` unless configured otherwise), so Jev is
 * available whenever OPENROUTER_API_KEY is set. Callers catch failures and
 * fall back to what they did without Jev.
 */
final readonly class Jev
{
    public function isAvailable(): bool
    {
        $key = config('ai.providers.openrouter.key');

        return is_string($key) && $key !== '';
    }

    /**
     * @param  string|array<string, mixed>  $state
     */
    public function ask(string|array $state, string $key, Question $question): Answer
    {
        return Classification::of($state)
            ->question($key, $question)
            ->timeout(config()->integer('quotes.ai.timeout'))
            ->classify(Lab::OpenRouter)
            ->answer($key);
    }
}
