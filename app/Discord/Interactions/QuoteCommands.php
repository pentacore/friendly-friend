<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

/**
 * The bot's application commands, registered per enabled guild.
 */
final class QuoteCommands
{
    public const string SLASH = 'quote';

    public const string ADD = 'add';

    public const string RANDOM = 'random';

    public const string IMPORT = 'import';

    public const string SAVE_MESSAGE = 'Save quote';

    private const int CHAT_INPUT = 1;

    private const int MESSAGE = 3;

    private const int SUB_COMMAND = 1;

    private const int STRING = 3;

    private const int USER = 6;

    private const int CHANNEL = 7;

    /** Guild install, used inside guilds only. */
    private const array GUILD_ONLY = ['integration_types' => [0], 'contexts' => [0]];

    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'type' => self::CHAT_INPUT,
                'name' => self::SLASH,
                'description' => 'Save, find and import quotes',
                ...self::GUILD_ONLY,
                'options' => [
                    [
                        'type' => self::SUB_COMMAND,
                        'name' => self::ADD,
                        'description' => 'Save something someone said',
                        'options' => [
                            ['type' => self::STRING, 'name' => 'text', 'description' => 'What was said (use "Name: text" lines for a conversation)', 'required' => true, 'max_length' => 2000],
                            ['type' => self::USER, 'name' => 'who', 'description' => 'Who said it'],
                            ['type' => self::STRING, 'name' => 'who_name', 'description' => 'Or: who said it, if they are not on Discord', 'max_length' => 80],
                            ['type' => self::STRING, 'name' => 'context', 'description' => 'Where or why it was said', 'max_length' => 300],
                            ['type' => self::STRING, 'name' => 'when', 'description' => 'When, e.g. "yesterday" or "2026-10-31"', 'max_length' => 80],
                        ],
                    ],
                    [
                        'type' => self::SUB_COMMAND,
                        'name' => self::RANDOM,
                        'description' => 'Post a random quote',
                        'options' => [
                            ['type' => self::USER, 'name' => 'person', 'description' => 'Only quotes by this person'],
                        ],
                    ],
                    [
                        'type' => self::SUB_COMMAND,
                        'name' => self::IMPORT,
                        'description' => 'Admins: find old quotes in a channel for review',
                        'options' => [
                            ['type' => self::CHANNEL, 'name' => 'channel', 'description' => 'Channel to scan', 'required' => true, 'channel_types' => [0, 5]],
                        ],
                    ],
                ],
            ],
            [
                'type' => self::MESSAGE,
                'name' => self::SAVE_MESSAGE,
                ...self::GUILD_ONLY,
            ],
        ];
    }
}
