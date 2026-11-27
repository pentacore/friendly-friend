import { usePage } from '@inertiajs/vue3';

/**
 * The locale, timezone and "now" the server sent with the page. Every date
 * and number on the site is formatted with these instead of the runtime's
 * defaults, so the server render and the browser produce the same text
 * (no hydration mismatches). Read through usePage(), like Inertia itself,
 * so each render uses its own page.
 */
function shared(): { locale?: string; timezone?: string; now?: string } {
    return (usePage().props ?? {}) as {
        locale?: string;
        timezone?: string;
        now?: string;
    };
}

export function displayLocale(): string {
    return shared().locale ?? 'en-GB';
}

/** The site's display timezone, for dates that don't belong to an event. */
export function displayTimeZone(): string {
    return shared().timezone ?? 'Europe/Stockholm';
}

/** "Now" as the server saw it when rendering the page. */
export function pageNow(): Date {
    const now = shared().now;

    return now ? new Date(now) : new Date();
}
