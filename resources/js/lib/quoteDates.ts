import type { SaidAtPrecision } from '@/typefinder';

const day = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});
const month = new Intl.DateTimeFormat(undefined, {
    month: 'long',
    year: 'numeric',
});

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
        ? `around ${month.format(date)}`
        : day.format(date);
}
