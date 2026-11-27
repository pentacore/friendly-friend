<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

/**
 * Every application command the bot registers in an enabled server.
 */
final class BotCommands
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [...QuoteCommands::definitions(), ...PrivacyCommands::definitions()];
    }
}
