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

const pad = (n: number) => String(n).padStart(2, '0');

/** Today as YYYY-MM-DD in the browser's timezone (for date pickers' min). */
export function localDate(date = new Date()): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Now as YYYY-MM-DDTHH:mm in the browser's timezone (for datetime pickers' min). */
export function localDateTime(date = new Date()): string {
    return `${localDate(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** The day after a YYYY-MM-DD date. */
export function nextDay(date: string): string {
    return addDays(date, 1);
}

/** A YYYY-MM-DD date plus some days. */
export function addDays(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);

    return localDate(new Date(year ?? 0, (month ?? 1) - 1, (day ?? 1) + days));
}

/** Whole days from one YYYY-MM-DD date to another. */
export function daysBetween(from: string, to: string): number {
    const toDate = (value: string) => {
        const [year, month, day] = value.split('-').map(Number);

        return Date.UTC(year ?? 0, (month ?? 1) - 1, day ?? 1);
    };

    return Math.round((toDate(to) - toDate(from)) / 86_400_000);
}

/** An ISO time as YYYY-MM-DDTHH:mm in the given timezone (to prefill datetime-local inputs). */
export function localInputIn(iso: string, timeZone: string): string {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(new Date(iso))
            .map((part) => [part.type, part.value]),
    );

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}
