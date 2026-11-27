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
 * private), help (DMed), or, by default, to quote what they wrote ("@bot
 * Martin said …"). A message can't be private, so the preview goes to their DMs.
 */
#[Tries(1)]
final class CaptureFromMention implements ShouldQueue
{
    use Queueable;

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
                $this->postRandomQuote($discord, $interactions, $guild, $member->discordId);

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
     * mentioned besides the bot if there is one.
     */
    private function postRandomQuote(DiscordClient $discord, QuoteInteractions $interactions, Guild $guild, string $askerId): void
    {
        $personId = array_key_first($this->otherMentionNames());
        $quote = $interactions->randomQuote($guild, $personId !== null ? (string) $personId : null);
        $channelId = $this->message['channel_id'] ?? null;
        $messageId = $this->message['id'] ?? null;

        if ($guild->private_random_quotes || ! is_string($channelId) || ! is_string($messageId)) {
            $discord->sendDirectMessage($askerId, $quote);

            return;
        }

        $discord->sendMessage($channelId, [...$quote, 'message_reference' => ['message_id' => $messageId]]);
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
