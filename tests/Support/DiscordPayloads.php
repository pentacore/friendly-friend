<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Builders for Discord gateway payloads used in tests (shapes as Discord
 * sends them).
 */
final class DiscordPayloads
{
    /**
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    public static function member(string $userId = '42', array $roles = ['100'], string $username = 'martin'): array
    {
        return [
            'user' => ['id' => $userId, 'username' => $username, 'global_name' => ucfirst($username), 'avatar' => null],
            'nick' => null,
            'avatar' => null,
            'roles' => $roles,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @param  array<string, mixed>  $resolved
     * @param  array<string, mixed>|null  $member
     * @return array<string, mixed>
     */
    public static function slash(string $subcommand, array $options = [], array $resolved = [], string $guildId = '555', ?array $member = null): array
    {
        return [
            'id' => 'int-1',
            'token' => 'tok-1',
            'type' => 2,
            'guild_id' => $guildId,
            'channel_id' => '777',
            'member' => $member ?? self::member(),
            'data' => [
                'name' => 'quote',
                'options' => [['type' => 1, 'name' => $subcommand, 'options' => $options]],
                'resolved' => $resolved,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>|null  $member
     * @return array<string, mixed>
     */
    public static function saveQuoteMenu(array $message, string $guildId = '555', ?array $member = null): array
    {
        return [
            'id' => 'int-2',
            'token' => 'tok-2',
            'type' => 2,
            'guild_id' => $guildId,
            'channel_id' => '777',
            'member' => $member ?? self::member(),
            'data' => [
                'name' => 'Save quote',
                'type' => 3,
                'target_id' => $message['id'],
                'resolved' => ['messages' => [$message['id'] => $message]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function message(string $id = '999', string $content = 'im not drunk im philosophical', string $authorId = '50', array $extra = []): array
    {
        return [
            'id' => $id,
            'channel_id' => '777',
            'content' => $content,
            'timestamp' => '2026-11-20T21:15:00.000000+00:00',
            'author' => ['id' => $authorId, 'username' => 'bo', 'global_name' => 'Bo', 'avatar' => null],
            'mentions' => [],
            ...$extra,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function button(string $customId, string $userId = '42', bool $inDm = false): array
    {
        return [
            'id' => 'int-3',
            'token' => 'tok-3',
            'type' => 3,
            'channel_id' => '777',
            ...($inDm ? ['user' => self::member($userId)['user']] : ['guild_id' => '555', 'member' => self::member($userId)]),
            'data' => ['custom_id' => $customId, 'component_type' => 2],
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    public static function modal(string $customId, array $fields, string $userId = '42'): array
    {
        return [
            'id' => 'int-4',
            'token' => 'tok-4',
            'type' => 5,
            'guild_id' => '555',
            'channel_id' => '777',
            'member' => self::member($userId),
            'data' => [
                'custom_id' => $customId,
                'components' => array_map(
                    fn (string $id, string $value): array => ['type' => 1, 'components' => [['type' => 4, 'custom_id' => $id, 'value' => $value]]],
                    array_keys($fields),
                    $fields,
                ),
            ],
        ];
    }
}
