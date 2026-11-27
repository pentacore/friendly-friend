<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\MentionIntent;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Throwable;

/**
 * Decides what an @mention of the bot wants (save a quote, a random quote,
 * find a quote, help), so `CaptureFromMention` can route it. Capturing is the safe default:
 * it ends in a private preview that can be cancelled. So anything else needs
 * Jev to be fairly sure, and no Jev (or a failing one) means capture.
 */
final readonly class MentionIntentClassifier
{
    /** Below this, Jev's "not a capture" answer is ignored. */
    private const float MIN_PROBABILITY = 0.6;

    public function __construct(private Jev $jev) {}

    public function intent(string $text): MentionIntent
    {
        if (! $this->jev->isAvailable()) {
            return MentionIntent::Capture;
        }

        try {
            $answer = $this->jev->ask($text, 'intent', new Choice(
                'Someone @mentioned a quote bot in a Discord channel with this message (the mention itself removed). What do they want?',
                [
                    MentionIntent::Capture->value => 'Save a quote: the message records what someone said ("Martin said ...", "Bo: ...", "\"words\" - Name"), or anything else.',
                    MentionIntent::RandomQuote->value => 'Be shown a random saved quote, maybe from a specific person ("random quote", "give me a quote from @Bo").',
                    MentionIntent::Search->value => 'Find a specific saved quote by its words, person or topic ("find the one where Bo talks about IKEA", "who said \"i can fix it\"?", "hitta citatet om kanelbullar").',
                    MentionIntent::Help->value => 'Help: what the bot can do or how to use it ("help", "what can you do?").',
                ],
            ));
        } catch (Throwable $e) {
            Log::warning('Jev mention classification failed; capturing.', ['exception' => $e->getMessage()]);

            return MentionIntent::Capture;
        }

        if (! $answer instanceof ChoiceAnswer) {
            return MentionIntent::Capture;
        }

        $intent = MentionIntent::tryFrom($answer->choice) ?? MentionIntent::Capture;

        return $answer->probabilityOf($answer->choice) >= self::MIN_PROBABILITY ? $intent : MentionIntent::Capture;
    }
}
