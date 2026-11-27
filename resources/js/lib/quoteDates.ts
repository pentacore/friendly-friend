import { displayLocale, displayTimeZone } from '@/lib/locale';
import type { SaidAtPrecision } from '@/typefinder';

function format(date: Date, options: Intl.DateTimeFormatOptions): string {
    return new Intl.DateTimeFormat(displayLocale(), {
        timeZone: displayTimeZone(),
        ...options,
    }).format(date);
}

/** When a quote was said, as precise as we know it. */
export function formatSaidAt(
    saidAt: string | null,
    precision: SaidAtPrecision | null,
): string | null {
    if (saidAt === null) {
        return null;
    }

    const date = new Date(saidAt);

    return precision === 'approximate'
        ? `around ${format(date, { month: 'long', year: 'numeric' })}`
        : format(date, { day: 'numeric', month: 'short', year: 'numeric' });
}
