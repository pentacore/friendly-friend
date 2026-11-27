<?php

declare(strict_types=1);

namespace App\Quotes\Jobs;

use App\Access\Actions\RecordGuildMember;
use App\Access\GuildAccess;
use App\Ai\CaptureInput;
use App\Ai\MentionIntentClassifier;
use App\Ai\QuoteNormalizer;
use App\Discord\Data\GuildMemberData;
use App\Discord\Data\SentMessage;
use App\Discord\DiscordClient;
use App\Discord\Interactions\DraftEditForm;
use App\Enums\MentionIntent;
use App\Enums\QuoteSource;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Quote;
use App\Quotes\Actions\CancelDraft;
use App\Quotes\Actions\DraftQuote;
use App\Quotes\Actions\ResolvePerson;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\ResolvedSpeaker;
use App\Quotes\Discord\QuoteInteractions;
use App\Quotes\Discord\QuoteMessages;
use App\Quotes\Exceptions\AlreadyQuotedException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * Someone mentioned the bot. Replying to a message: quote that message.
 * Otherwise Jev (`MentionIntentClassifier`) decides what they want: a random
 * quote (posted in the channel, or DMed if the server keeps random quotes
 * private), a search ("find the one where …", results DMed like the private
 * `/quote search`), help (DMed), or, by default, to quote what they wrote ("@bot
 * Martin said …"). A message can't be private, so the preview goes to their DMs.
 */
#[Tries(1)]
final class CaptureFromMention implements ShouldQueue
{
    use Queueable;

    /** Words that ask for a search rather than describe the quote (English and Swedish). */
    private const array ASKING_WORDS = [
        'a', 'about', 'an', 'by', 'can', 'could', 'find', 'for', 'from', 'get', 'give', 'i', 'is', 'look', 'me', 'my', 'of',
        'one', 'please', 'pls', 'plz', 'quote', 'quotes', 'said', 'say', 'says', 'search', 'show', 'someone', 'talked', 'talks',
        'that', 'the', 'there', 'up', 'was', 'when', 'where', 'who', 'with', 'you',
        'av', 'citat', 'citaten', 'citatet', 'där', 'den', 'det', 'du', 'efter', 'en', 'ett', 'från', 'hitta', 'jag', 'kan',
        'leta', 'med', 'mig', 'när', 'om', 'pratar', 'sa', 'sade', 'snälla', 'som', 'säger', 'sök', 'tack', 'var', 'visa',
    ];

    /**
     * @param  array<mixed>  $message  MESSAGE_CREATE
     */
    public function __construct(public readonly array $message) {}

    public function handle(
        DiscordClient $discord,
        GuildAccess $access,
        RecordGuildMember $recordMember,
        QuoteInteractions $interactions,
        QuoteNormalizer $normalizer,
        DraftQuote $draftQuote,
        CancelDraft $cancelDraft,
        DraftEditForm $editForm,
        MentionIntentClassifier $intents,
        ResolvePerson $resolvePerson,
    ): void {
        $message = $this->message;
        $guild = Guild::query()->enabled()->where('discord_id', $message['guild_id'] ?? '')->first();
        $author = is_array($message['author'] ?? null) ? $message['author'] : null;

        if ($guild === null || $author === null) {
            return;
        }

        $member = GuildMemberData::fromPayload([...(is_array($message['member'] ?? null) ? $message['member'] : []), 'user' => $author]);

        if (! $member->isValid()) {
            return;
        }

        $submitter = $recordMember->handle($guild, $member)->discordUser;

        if (! $access->allowsInGuild($submitter, $guild)) {
            return;
        }

        $referenced = is_array($message['referenced_message'] ?? null) ? $message['referenced_message'] : null;
        $text = $this->textWithoutBotMention();

        if ($referenced === null) {
            if ($text === '') {
                return;
            }

            $intent = $intents->intent($text);

            if ($intent === MentionIntent::RandomQuote) {
                $this->postRandomQuote($discord, $interactions, $resolvePerson, $guild, $member->discordId, $text);

                return;
            }

            if ($intent === MentionIntent::Search) {
                $discord->sendDirectMessage($member->discordId, $this->searchReply($interactions, $guild, $text));

                return;
            }

            if ($intent === MentionIntent::Help) {
                $discord->sendDirectMessage($member->discordId, QuoteMessages::help());

                return;
            }
        }

        try {
            $draft = $referenced !== null
                ? $interactions->draftFromMessage($guild, $submitter, $referenced, QuoteSource::ReplyMention)
                : $this->draftFromText($guild, $submitter, $normalizer, $draftQuote, $text);
        } catch (AlreadyQuotedException) {
            $draft = 'That message is already saved as a quote.';
        }

        if (is_string($draft)) {
            $discord->sendDirectMessage($member->discordId, QuoteMessages::notice($draft));

            return;
        }

        $draft->load('lines');
        $editForm->remember($draft);

        $sent = $discord->sendDirectMessage($member->discordId, QuoteMessages::preview($draft));

        if (! $sent instanceof SentMessage) {
            $cancelDraft->handle($draft);
            $this->replyDmsClosed($discord, $member->discordId);

            return;
        }

        // Kept so the preview can say "expired" when the draft is pruned.
        $draft->forceFill(['preview_channel_id' => $sent->channelId, 'preview_message_id' => $sent->messageId])->saveQuietly();
    }

    private function textWithoutBotMention(): string
    {
        $applicationId = config()->string('services.discord.client_id');

        return trim((string) preg_replace('/<@!?'.preg_quote($applicationId, '/').'>/', '', is_string($this->message['content'] ?? null) ? $this->message['content'] : ''));
    }

    /**
     * Names of the people mentioned besides the bot, keyed by Discord ID.
     *
     * @return array<string, string>
     */
    private function otherMentionNames(): array
    {
        $applicationId = config()->string('services.discord.client_id');

        return array_filter(
            QuoteInteractions::mentionNames($this->message['mentions'] ?? null),
            fn (string $name, int|string $id): bool => (string) $id !== $applicationId,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Replies in the channel with a random quote, from the first person
     * mentioned besides the bot, or else a person named after "from"/"by"
     * ("a quote from Uncle Rolf"). A name nobody matches means anyone.
     */
    private function postRandomQuote(DiscordClient $discord, QuoteInteractions $interactions, ResolvePerson $resolvePerson, Guild $guild, string $askerId, string $text): void
    {
        $personId = array_key_first($this->otherMentionNames());
        $name = $personId === null ? $this->namedPerson($text) : null;
        $speaker = $name !== null ? $resolvePerson->handle($guild, new NormalizedLine($name, '')) : null;
        $quote = $interactions->randomQuote(
            $guild,
            $personId !== null ? (string) $personId : null,
            $speaker instanceof ResolvedSpeaker && ! $speaker->isNew() ? $speaker : null,
        );
        $channelId = $this->message['channel_id'] ?? null;
        $messageId = $this->message['id'] ?? null;

        if ($guild->private_random_quotes || ! is_string($channelId) || ! is_string($messageId)) {
            $discord->sendDirectMessage($askerId, $quote);

            return;
        }

        $discord->sendMessage($channelId, [...$quote, 'message_reference' => ['message_id' => $messageId]]);
    }

    /**
     * The name after "from", "by" or "of" (Swedish "från", "av"), without
     * trailing politeness and punctuation: "a quote from Bo please" → "Bo".
     */
    private function namedPerson(string $text): ?string
    {
        if (preg_match('/\b(?:from|by|of|från|av)\s+(.+)$/iu', $text, $match) !== 1) {
            return null;
        }

        $name = trim((string) preg_replace('/(?:[\s,]+(?:please|pls|plz|tack|thanks))+$|[\s.!?,]+$/iu', '', $match[1]));
        $name = trim((string) preg_replace('/[\s.!?,]+$/u', '', $name));

        return $name !== '' && mb_strlen($name) <= 40 ? $name : null;
    }

    /**
     * Search results for "@bot find the one where Bo talks about IKEA":
     * the quoted words if there are any, else the message without the
     * asking words, narrowed to the first member mentioned besides the bot.
     *
     * @return array<string, mixed>
     */
    private function searchReply(QuoteInteractions $interactions, Guild $guild, string $text): array
    {
        $words = $this->searchWords($text);

        if ($words === '') {
            return QuoteMessages::notice('What should I look for? Try "@me find the one about kanelbullar", or /quote search.');
        }

        $personId = array_key_first($this->otherMentionNames());

        return $interactions->search($guild, $words, $personId !== null ? (string) $personId : null);
    }

    /**
     * The words worth searching for in a request: the quoted part
     * ("who said "i can fix it"?") or every word that isn't part of asking.
     */
    private function searchWords(string $text): string
    {
        $text = (string) preg_replace('/<[@#][!&]?\d+>/', ' ', $text);

        if (preg_match('/["“”„](.+?)["“”]/u', $text, $quoted) === 1) {
            return trim($quoted[1]);
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_filter($words, fn (string $word): bool => ! in_array(mb_strtolower($word), self::ASKING_WORDS, true)));
    }

    private function draftFromText(Guild $guild, DiscordUser $submitter, QuoteNormalizer $normalizer, DraftQuote $draftQuote, string $text): Quote
    {
        $mentionNames = $this->otherMentionNames();

        $normalized = $normalizer->normalize(new CaptureInput(text: $text, mentionNames: $mentionNames));

        return $draftQuote->handle($guild, $submitter, QuoteSource::MentionFreetext, $text, $normalized);
    }

    private function replyDmsClosed(DiscordClient $discord, string $userId): void
    {
        $channelId = $this->message['channel_id'] ?? null;
        $messageId = $this->message['id'] ?? null;

        if (! is_string($channelId) || ! is_string($messageId)) {
            return;
        }

        $discord->sendMessage($channelId, [
            'content' => "<@{$userId}> I couldn't DM you the quote preview. Allow DMs from server members, or use /quote add.",
            'message_reference' => ['message_id' => $messageId],
            'allowed_mentions' => ['users' => [$userId]],
        ]);
    }
}
