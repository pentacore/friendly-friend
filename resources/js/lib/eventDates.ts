/** Dates of an event, always shown in the event's own timezone. */

function parts(
    iso: string,
    timeZone: string,
    options: Intl.DateTimeFormatOptions,
): string {
    return new Intl.DateTimeFormat(undefined, { timeZone, ...options }).format(
        new Date(iso),
    );
}

/** "Sat 5 Dec, 18:00–23:30" (or across days: "Sat 5 Dec, 18:00 – Sun 6 Dec, 12:00"). */
export function formatEventWhen(
    startsAt: string,
    endsAt: string | null,
    timeZone: string,
): string {
    const day = (iso: string) =>
        parts(iso, timeZone, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        });
    const time = (iso: string) =>
        parts(iso, timeZone, { hour: '2-digit', minute: '2-digit' });
    const start = `${day(startsAt)}, ${time(startsAt)}`;

    if (endsAt === null) {
        return start;
    }

    return day(startsAt) === day(endsAt)
        ? `${start}–${time(endsAt)}`
        : `${start} – ${day(endsAt)}, ${time(endsAt)}`;
}

/** The pieces of the date badge. */
export function eventDateBadge(
    startsAt: string,
    timeZone: string,
): { day: string; month: string; weekday: string } {
    return {
        day: parts(startsAt, timeZone, { day: 'numeric' }),
        month: parts(startsAt, timeZone, { month: 'short' }),
        weekday: parts(startsAt, timeZone, { weekday: 'short' }),
    };
}

/** "in 3 days", "tomorrow", "2 weeks ago". */
export function relativeToNow(iso: string): string {
    const days = Math.round(
        (new Date(iso).getTime() - Date.now()) / 86_400_000,
    );
    const format = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    return Math.abs(days) >= 14
        ? format.format(Math.round(days / 7), 'week')
        : format.format(days, 'day');
}
