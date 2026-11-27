<?php

declare(strict_types=1);

namespace App\Quotes\Discord;

use App\Access\Actions\RecordDiscordUser;
use App\Access\Actions\RecordGuildMember;
use App\Access\GuildAccess;
use App\Ai\CaptureInput;
use App\Ai\QuoteNormalizer;
use App\Discord\Data\DiscordUserData;
use App\Discord\Data\GuildMemberData;
use App\Discord\Data\Payload;
use App\Discord\DiscordClient;
use App\Discord\Interactions\CustomId;
use App\Discord\Interactions\DraftEditForm;
use App\Discord\Interactions\InteractionAck;
use App\Discord\Interactions\QuoteCommands;
use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Quote;
use App\Quotes\Actions\CancelDraft;
use App\Quotes\Actions\ConfirmQuote;
use App\Quotes\Actions\DraftQuote;
use App\Quotes\Actions\PublishQuote;
use App\Quotes\Actions\UpdateDraft;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\SourceMessage;
use App\Quotes\Exceptions\AlreadyQuotedException;
use App\Quotes\Imports\StartChannelImport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles quote interactions after the bot has acknowledged them: `/quote`,
 * the "Save quote" context menu, the preview buttons and the edit form. The
 * result always replaces the acknowledged (deferred) response.
 */
final readonly class QuoteInteractions
{
    public function __construct(
        private DiscordClient $discord,
        private GuildAccess $access,
        private RecordGuildMember $recordMember,
        private RecordDiscordUser $recordUser,
        private QuoteNormalizer $normalizer,
        private DraftQuote $draftQuote,
        private UpdateDraft $updateDraft,
        private ConfirmQuote $confirmQuote,
        private CancelDraft $cancelDraft,
        private PublishQuote $publishQuote,
        private DraftEditForm $editForm,
        private StartChannelImport $startImport,
    ) {}

    /**
     * @param  array<mixed>  $payload  INTERACTION_CREATE
     */
    public function handle(array $payload): void
    {
        $token = is_string($payload['token'] ?? null) ? $payload['token'] : '';
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        try {
            $reply = match ($payload['type'] ?? null) {
                InteractionAck::APPLICATION_COMMAND => $this->command($payload, $data),
                InteractionAck::MESSAGE_COMPONENT => $this->button($payload, $data),
                InteractionAck::MODAL_SUBMIT => $this->editSubmitted($payload, $data),
                default => null,
            };
        } catch (Throwable $e) {
            Log::error('Quote interaction failed.', ['exception' => $e]);
            $reply = QuoteMessages::notice('Something went wrong. Please try again.');
        }

        if ($reply !== null && $token !== '') {
            $this->discord->editInteractionResponse($token, $reply);
        }
    }

    /**
     * Start a draft from a Discord message (context menu or reply + mention).
     *
     * @param  array<mixed>  $message  Discord message object
     *
     * @throws AlreadyQuotedException
     */
    public function draftFromMessage(Guild $guild, DiscordUser $submitter, array $message, QuoteSource $source): Quote|string
    {
        $author = is_array($message['author'] ?? null) ? DiscordUserData::fromPayload($message['author']) : null;
        $content = is_string($message['content'] ?? null) ? trim($message['content']) : '';

        if (! $author instanceof DiscordUserData || ! $author->isValid() || ($message['author']['bot'] ?? false) === true) {
            return "I can't quote bots.";
        }

        if ($content === '') {
            return 'That message has no text to quote.';
        }

        $authorUser = $this->recordUser->handle($author);
        $messageId = is_string($message['id'] ?? null) ? $message['id'] : '';
        $channelId = is_string($message['channel_id'] ?? null) ? $message['channel_id'] : '';
        $sentAt = is_string($message['timestamp'] ?? null) ? CarbonImmutable::parse($message['timestamp']) : null;

        $normalized = $this->normalizer->normalize(new CaptureInput(
            text: $content,
            knownSpeaker: new NormalizedLine($authorUser->display_name, '', $authorUser->discord_id),
            mentionNames: self::mentionNames($message['mentions'] ?? null),
            messageTime: $sentAt,
        ));

        return $this->draftQuote->handle(
            $guild,
            $submitter,
            $source,
            $content,
            $normalized,
            new SourceMessage($messageId, $channelId, "https://discord.com/channels/{$guild->discord_id}/{$channelId}/{$messageId}"),
        );
    }

    /**
     * Discord ID → display name for the users mentioned in a message.
     *
     * @return array<string, string>
     */
    public static function mentionNames(mixed $mentions): array
    {
        $names = [];

        foreach (is_array($mentions) ? $mentions : [] as $mention) {
            if (! is_array($mention)) {
                continue;
            }

            $user = DiscordUserData::fromPayload($mention);
            $nick = is_array($mention['member'] ?? null) && is_string($mention['member']['nick'] ?? null) ? $mention['member']['nick'] : null;

            if ($user->isValid()) {
                $names[$user->discordId] = $nick ?? $user->globalName ?? $user->username;
            }
        }

        return $names;
    }

    /**
     * @param  array<mixed>  $payload
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private function command(array $payload, array $data): array
    {
        $guild = Guild::query()->enabled()->where('discord_id', $payload['guild_id'] ?? '')->first();

        if ($guild === null) {
            return QuoteMessages::notice("Quotes aren't enabled on this server yet. An owner can enable it on the website.");
        }

        $actor = $this->actorInGuild($guild, $payload);

        if (! $actor instanceof DiscordUser || ! $this->access->allowsInGuild($actor, $guild)) {
            return QuoteMessages::notice("You don't have access to quotes on this server.");
        }

        if (($data['name'] ?? null) === QuoteCommands::SAVE_MESSAGE) {
            return $this->saveMessageCommand($guild, $actor, $data);
        }

        $subcommand = Payload::array($data, 'options', 0);
        $options = $this->options($subcommand['options'] ?? null);
        $resolvedUsers = Payload::array($data, 'resolved', 'users');

        return match ($subcommand['name'] ?? null) {
            QuoteCommands::ADD => $this->addCommand($guild, $actor, $options, $resolvedUsers),
            QuoteCommands::RANDOM => $this->randomCommand($guild, $options),
            QuoteCommands::IMPORT => $this->importCommand($guild, $actor, $options),
            default => QuoteMessages::notice('Unknown command.'),
        };
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private function saveMessageCommand(Guild $guild, DiscordUser $actor, array $data): array
    {
        $targetId = Payload::string($data, 'target_id');
        $message = $targetId !== null ? Payload::array($data, 'resolved', 'messages', $targetId) : [];

        if ($message === []) {
            return QuoteMessages::notice("I couldn't read that message.");
        }

        return $this->previewOrNotice(fn (): Quote|string => $this->draftFromMessage($guild, $actor, $message, QuoteSource::ContextMenu));
    }

    /**
     * @param  array<string, string>  $options
     * @param  array<mixed>  $resolvedUsers
     * @return array<string, mixed>
     */
    private function addCommand(Guild $guild, DiscordUser $actor, array $options, array $resolvedUsers): array
    {
        $speakerHint = null;
        $mentionNames = [];

        if (isset($options['who']) && is_array($resolvedUsers[$options['who']] ?? null)) {
            $who = $this->recordUser->handle(DiscordUserData::fromPayload($resolvedUsers[$options['who']]));
            $speakerHint = new NormalizedLine($who->display_name, '', $who->discord_id);
            $mentionNames[$who->discord_id] = $who->display_name;
        } elseif (isset($options['who_name'])) {
            $speakerHint = new NormalizedLine($options['who_name'], '');
        }

        $text = $options['text'] ?? '';

        $normalized = $this->normalizer->normalize(new CaptureInput(
            text: $text,
            speakerHint: $speakerHint,
            mentionNames: $mentionNames,
            context: $options['context'] ?? null,
            when: $options['when'] ?? null,
        ));

        return $this->previewOrNotice(fn (): Quote => $this->draftQuote->handle($guild, $actor, QuoteSource::SlashCommand, $text, $normalized));
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private function randomCommand(Guild $guild, array $options): array
    {
        $quote = Quote::query()
            ->whereBelongsTo($guild)
            ->where('status', QuoteStatus::Published)
            ->when($options['person'] ?? null, fn (Builder $query, string $discordId) => $query->whereHas(
                'lines.person.discordUser',
                fn (Builder $user) => $user->where('discord_id', $discordId),
            ))
            ->inRandomOrder()
            ->with(['lines', 'submittedBy'])
            ->first();

        return $quote !== null
            ? QuoteMessages::published($quote, '— quote #'.$quote->id)
            : QuoteMessages::notice('No quotes yet. Save one with /quote add or right-click a message → Apps → Save quote.');
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private function importCommand(Guild $guild, DiscordUser $actor, array $options): array
    {
        if (! $this->access->isAdminInGuild($actor, $guild)) {
            return QuoteMessages::notice('Only admins can import quotes.');
        }

        $channelId = $options['channel'] ?? '';
        $import = $this->startImport->handle($guild, $actor, $channelId);

        return QuoteMessages::notice("Scanning <#{$channelId}> for quotes (import #{$import->id}). Found quotes will wait for review on the website.");
    }

    /**
     * @param  array<mixed>  $payload
     * @param  array<mixed>  $data
     * @return array<string, mixed>|null
     */
    private function button(array $payload, array $data): ?array
    {
        $customId = CustomId::parse($data['custom_id'] ?? null);

        if (! $customId instanceof CustomId || $customId->action === CustomId::EDIT) {
            // Edit was answered with the form (or an "expired" note) by the bot.
            return null;
        }

        $quote = $this->ownDraft($payload, $customId->quoteId);

        if (! $quote instanceof Quote) {
            return $quote;
        }

        DraftEditForm::forget($quote->id);

        if ($customId->action === CustomId::CANCEL) {
            $this->cancelDraft->handle($quote);

            return QuoteMessages::notice('Cancelled. Nothing was saved.');
        }

        $this->confirmQuote->handle($quote, $quote->submittedBy);
        $quote->load(['lines', 'guild', 'submittedBy']);
        $this->publishQuote->handle($quote);

        return QuoteMessages::published($quote, '**Saved** ✅');
    }

    /**
     * @param  array<mixed>  $payload
     * @param  array<mixed>  $data
     * @return array<string, mixed>|null
     */
    private function editSubmitted(array $payload, array $data): ?array
    {
        $customId = CustomId::parse($data['custom_id'] ?? null);

        if (! $customId instanceof CustomId) {
            return null;
        }

        $quote = $this->ownDraft($payload, $customId->quoteId);

        if (! $quote instanceof Quote) {
            return $quote;
        }

        $content = $this->editForm->parse($this->modalFields($data['components'] ?? null));

        if ($content->lines !== []) {
            $this->updateDraft->handle($quote, $content);
        }

        $quote->load('lines');
        $this->editForm->remember($quote);

        return QuoteMessages::preview($quote, 'Updated.');
    }

    /**
     * The draft behind a button or form, if it still exists and belongs to
     * whoever clicked; otherwise the message to show instead.
     *
     * @param  array<mixed>  $payload
     * @return Quote|array<string, mixed>
     */
    private function ownDraft(array $payload, int $quoteId): Quote|array
    {
        $quote = Quote::query()->with(['lines', 'submittedBy', 'guild'])->find($quoteId);

        if ($quote === null || $quote->status !== QuoteStatus::Draft) {
            return QuoteMessages::notice('This draft has expired or was already handled.');
        }

        if ($this->actorId($payload) !== $quote->submittedBy->discord_id) {
            return QuoteMessages::notice('Only the person who started this quote can save or change it.');
        }

        return $quote;
    }

    /**
     * @param  callable(): (Quote|string)  $draft
     * @return array<string, mixed>
     */
    private function previewOrNotice(callable $draft): array
    {
        try {
            $quote = $draft();
        } catch (AlreadyQuotedException) {
            return QuoteMessages::notice('That message is already saved as a quote.');
        }

        if (is_string($quote)) {
            return QuoteMessages::notice($quote);
        }

        $quote->load('lines');
        $this->editForm->remember($quote);

        return QuoteMessages::preview($quote);
    }

    /**
     * Records the invoking member (fresh roles) and returns their account.
     *
     * @param  array<mixed>  $payload
     */
    private function actorInGuild(Guild $guild, array $payload): ?DiscordUser
    {
        $member = is_array($payload['member'] ?? null) ? GuildMemberData::fromPayload($payload['member']) : null;

        return $member instanceof GuildMemberData && $member->isValid()
            ? $this->recordMember->handle($guild, $member)->discordUser
            : null;
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function actorId(array $payload): ?string
    {
        return Payload::string($payload, 'member', 'user', 'id') ?? Payload::string($payload, 'user', 'id');
    }

    /**
     * Slash command options as name → value strings.
     *
     * @return array<string, string>
     */
    private function options(mixed $options): array
    {
        $values = [];

        foreach (is_array($options) ? $options : [] as $option) {
            if (is_array($option) && is_string($option['name'] ?? null) && (is_string($option['value'] ?? null) || is_int($option['value'] ?? null))) {
                $values[$option['name']] = trim((string) $option['value']);
            }
        }

        return array_filter($values, fn (string $value): bool => $value !== '');
    }

    /**
     * Submitted form values as custom_id → value.
     *
     * @return array<string, string>
     */
    private function modalFields(mixed $rows): array
    {
        $fields = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            foreach (is_array($row) && is_array($row['components'] ?? null) ? $row['components'] : [] as $input) {
                if (is_array($input) && is_string($input['custom_id'] ?? null) && is_string($input['value'] ?? null)) {
                    $fields[$input['custom_id']] = $input['value'];
                }
            }
        }

        return $fields;
    }
}
