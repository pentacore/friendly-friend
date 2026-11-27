<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

/**
 * `/privacy`: see, stop or restart stats tracking for yourself. Always
 * answered privately.
 */
final class PrivacyCommands
{
    public const string SLASH = 'privacy';

    public const string STATUS = 'status';

    public const string OPT_OUT = 'optout';

    public const string OPT_IN = 'optin';

    private const int CHAT_INPUT = 1;

    private const int SUB_COMMAND = 1;

    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'type' => self::CHAT_INPUT,
                'name' => self::SLASH,
                'description' => 'Server stats about you: see, stop or restart',
                'integration_types' => [0],
                'contexts' => [0],
                'options' => [
                    ['type' => self::SUB_COMMAND, 'name' => self::STATUS, 'description' => 'Are stats counted for you?'],
                    ['type' => self::SUB_COMMAND, 'name' => self::OPT_OUT, 'description' => 'Stop counting your activity and delete what was counted'],
                    ['type' => self::SUB_COMMAND, 'name' => self::OPT_IN, 'description' => 'Start counting your activity again'],
                ],
            ],
        ];
    }
}
