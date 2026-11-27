const number = new Intl.NumberFormat();

/** 1234 → "1,234" (in the viewer's locale). */
export function formatNumber(value: number): string {
    return number.format(value);
}

/** Seconds as "3 h 20 min", "45 min" or "under a minute". */
export function formatDuration(seconds: number): string {
    const minutes = Math.floor(seconds / 60);

    if (minutes < 1) {
        return 'under a minute';
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return rest === 0 || hours >= 10
        ? `${number.format(hours)} h`
        : `${hours} h ${rest} min`;
}

export function plural(count: number, one: string, many: string): string {
    return `${formatNumber(count)} ${count === 1 ? one : many}`;
}
