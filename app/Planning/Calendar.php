<?php

declare(strict_types=1);

namespace App\Planning;

use App\Models\Event;
use Carbon\CarbonInterface;

/**
 * Events as an iCalendar file (RFC 5545), for "Add to calendar" and the
 * personal feed. Times are UTC; text is escaped and lines folded at 75 octets.
 */
final class Calendar
{
    /** Without an end time, calendars get the same 6 hours the site assumes. */
    private const int ASSUMED_HOURS = 6;

    public static function ics(Event $event): string
    {
        return self::feed([$event]);
    }

    /**
     * Several events in one calendar, for the personal feed. With a name,
     * calendar apps show it as the subscribed calendar's title.
     *
     * @param  iterable<Event>  $events
     */
    public static function feed(iterable $events, ?string $name = null): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Friendly Friend//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            ...($name !== null ? ['X-WR-CALNAME:'.self::text($name), 'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H'] : []),
        ];

        foreach ($events as $event) {
            array_push($lines, ...self::vevent($event));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * @return list<string>
     */
    private static function vevent(Event $event): array
    {
        $end = $event->ends_at ?? $event->starts_at->copy()->addHours(self::ASSUMED_HOURS);
        $url = route('events.show', $event);
        $host = parse_url(config()->string('app.url'), PHP_URL_HOST);

        return [
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.(is_string($host) && $host !== '' ? $host : 'friendly-friend'),
            'DTSTAMP:'.self::time(now()),
            'LAST-MODIFIED:'.self::time($event->updated_at ?? now()),
            'DTSTART:'.self::time($event->starts_at),
            'DTEND:'.self::time($end),
            'SUMMARY:'.self::text($event->title),
            'URL:'.$url,
            'DESCRIPTION:'.self::text(trim(($event->description ?? '')."\n\n".$url)),
            ...($event->location !== null ? ['LOCATION:'.self::text($event->location)] : []),
            'STATUS:'.($event->isCancelled() ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
        ];
    }

    private static function time(CarbonInterface $at): string
    {
        return $at->copy()->utc()->format('Ymd\THis\Z');
    }

    /**
     * Escape TEXT values: backslash, semicolon, comma and newlines.
     */
    private static function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /**
     * Fold lines longer than 75 octets, without splitting a UTF-8 character.
     */
    private static function fold(string $line): string
    {
        $folded = '';
        $current = '';

        foreach (mb_str_split($line) as $char) {
            if (strlen($current.$char) > 75) {
                $folded .= $current."\r\n ";
                $current = '';
            }

            $current .= $char;
        }

        return $folded.$current;
    }
}
