<?php

declare(strict_types=1);

namespace App\Stats\Discord;

use App\Access\Actions\RecordDiscordUser;
use App\Discord\Data\DiscordUserData;
use App\Discord\Data\Payload;
use App\Discord\DiscordClient;
use App\Discord\Interactions\PrivacyCommands;
use App\Models\DiscordUser;
use App\Stats\Actions\OptIntoStats;
use App\Stats\Actions\OptOutOfStats;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Answers `/privacy status|optout|optin` after the bot acknowledged it
 * (privately). Works in any server the command is in, for anyone: opting
 * out never needs access to the website.
 */
final readonly class PrivacyInteractions
{
    public function __construct(
        private DiscordClient $discord,
        private RecordDiscordUser $recordUser,
        private OptOutOfStats $optOut,
        private OptIntoStats $optIn,
    ) {}

    /**
     * @param  array<mixed>  $payload  INTERACTION_CREATE
     */
    public function handle(array $payload): void
    {
        $token = Payload::string($payload, 'token');

        if ($token === null) {
            return;
        }

        try {
            $reply = $this->reply($payload);
        } catch (Throwable $e) {
            Log::error('Privacy command failed.', ['exception' => $e]);
            $reply = 'Something went wrong. Please try again, or use Settings → Privacy on the website.';
        }

        $this->discord->editInteractionResponse($token, ['content' => $reply, 'components' => []]);
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function reply(array $payload): string
    {
        $user = DiscordUserData::fromPayload(Payload::array($payload, 'member', 'user') ?: Payload::array($payload, 'user'));

        if (! $user->isValid()) {
            return "I couldn't tell who you are.";
        }

        $member = $this->recordUser->handle($user);

        return match (Payload::string($payload, 'data', 'options', 0, 'name')) {
            PrivacyCommands::OPT_OUT => $this->optOut($member),
            PrivacyCommands::OPT_IN => $this->optIn($member),
            default => $this->status($member),
        };
    }

    private function status(DiscordUser $member): string
    {
        return $member->tracking_opted_out_at === null
            ? "Stats are **on** for you: messages, reactions, voice time and games are counted (never what you write). Use `/privacy optout` to stop and delete what's been counted."
            : 'Stats are **off** for you since '.$member->tracking_opted_out_at->toDateString().'. Nothing is counted. Use `/privacy optin` to start again.';
    }

    private function optOut(DiscordUser $member): string
    {
        $this->optOut->handle($member);

        return "Done. Your activity isn't counted any more, and everything counted so far is being deleted. Your quotes stay. Changed your mind? `/privacy optin`.";
    }

    private function optIn(DiscordUser $member): string
    {
        $this->optIn->handle($member);

        return 'Stats are on again. Only activity from now on is counted.';
    }
}
