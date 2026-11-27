<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

use App\Discord\Data\Payload;

/**
 * Decides the immediate answer to an interaction, from the payload alone
 * (plus the cached edit form). The bot process sends it within Discord's 3
 * second limit; the real work happens in a queued job, which then edits
 * the response.
 */
final class InteractionAck
{
    public const int APPLICATION_COMMAND = 2;

    public const int MESSAGE_COMPONENT = 3;

    public const int MODAL_SUBMIT = 5;

    private const int MESSAGE = 4;

    private const int DEFERRED_MESSAGE = 5;

    private const int DEFERRED_UPDATE = 6;

    private const int MODAL = 9;

    private const int EPHEMERAL = 64;

    /**
     * The callback body, or null for interactions that aren't ours.
     *
     * @param  array<mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function body(array $payload): ?array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return match ($payload['type'] ?? null) {
            self::APPLICATION_COMMAND => self::command($data),
            self::MESSAGE_COMPONENT => self::component($data),
            self::MODAL_SUBMIT => CustomId::parse($data['custom_id'] ?? null) instanceof CustomId ? ['type' => self::DEFERRED_UPDATE] : null,
            default => null,
        };
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function command(array $data): ?array
    {
        $name = $data['name'] ?? null;

        if ($name === QuoteCommands::SAVE_MESSAGE || $name === PrivacyCommands::SLASH) {
            return self::deferred(ephemeral: true);
        }

        if ($name !== QuoteCommands::SLASH) {
            return null;
        }

        $subcommand = Payload::string($data, 'options', 0, 'name');

        // A random quote is for everyone in the channel; the rest is private.
        return self::deferred(ephemeral: $subcommand !== QuoteCommands::RANDOM);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function component(array $data): ?array
    {
        $eventComponent = EventCustomId::parse($data['custom_id'] ?? null);

        if ($eventComponent instanceof EventCustomId) {
            // Picker selects update the (private) picker in place; buttons get a private reply.
            return $eventComponent->isDateSelect() ? ['type' => self::DEFERRED_UPDATE] : self::deferred(ephemeral: true);
        }

        $customId = CustomId::parse($data['custom_id'] ?? null);

        if (! $customId instanceof CustomId) {
            return null;
        }

        if ($customId->action !== CustomId::EDIT) {
            return ['type' => self::DEFERRED_UPDATE];
        }

        $modal = DraftEditForm::cached($customId->quoteId);

        return $modal !== null
            ? ['type' => self::MODAL, 'data' => $modal]
            : ['type' => self::MESSAGE, 'data' => ['content' => 'This draft has expired. Start again to save the quote.', 'flags' => self::EPHEMERAL]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function deferred(bool $ephemeral): array
    {
        return $ephemeral
            ? ['type' => self::DEFERRED_MESSAGE, 'data' => ['flags' => self::EPHEMERAL]]
            : ['type' => self::DEFERRED_MESSAGE];
    }
}
