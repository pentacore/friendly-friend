<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a quote was captured from Discord.
 */
enum QuoteSource: string
{
    case ReplyMention = 'reply_mention';
    case ContextMenu = 'context_menu';
    case SlashCommand = 'slash_command';
    case MentionFreetext = 'mention_freetext';
    case ChannelImport = 'channel_import';
}
