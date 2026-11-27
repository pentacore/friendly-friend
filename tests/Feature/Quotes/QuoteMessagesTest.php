<?php

declare(strict_types=1);

use App\Discord\Testing\FakeDiscordClient;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Quotes\Discord\QuoteMessages;

function quoteWithLines(array $lines): Quote
{
    $quote = Quote::factory()->create(['context' => null, 'said_at' => null]);

    foreach ($lines as $position => [$speaker, $text]) {
        QuoteLine::factory()->for($quote)->create(['position' => $position, 'speaker_name' => $speaker, 'text' => $text, 'person_id' => null, 'speaker_discord_user_id' => null]);
    }

    return $quote->load('lines');
}

it('shows a single-speaker quote as "text" - Person', function (): void {
    $message = QuoteMessages::published(quoteWithLines([['Martin', "I'm not drunk, I'm philosophical"]]));

    expect(FakeDiscordClient::content($message))->toBe("\"I'm not drunk, I'm philosophical\" - Martin");
});

it('keeps one speaker\'s several lines in one quote', function (): void {
    $message = QuoteMessages::published(quoteWithLines([['Martin', 'one'], ['Martin', 'two']]));

    expect(FakeDiscordClient::content($message))->toBe("\"one\ntwo\" - Martin");
});

it('shows a conversation as Name: text lines', function (): void {
    $message = QuoteMessages::published(quoteWithLines([['Martin', 'I never said that'], ['Bo', 'You did']]));

    expect(FakeDiscordClient::content($message))->toBe("**Martin:** I never said that\n**Bo:** You did");
});

it('marks new people in the preview', function (): void {
    $message = QuoteMessages::preview(quoteWithLines([['Uncle Bo', 'cheese is a vegetable']]));

    expect(FakeDiscordClient::content($message))->toContain('"cheese is a vegetable" - Uncle Bo *(new person)*');
});
