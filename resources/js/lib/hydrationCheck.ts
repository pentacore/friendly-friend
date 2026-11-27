/**
 * Keeps Vue's hydration warnings and errors ("Hydration … mismatch") in
 * window.__hydrationMismatches as well as printing them, so the browser
 * tests can check a server-rendered page hydrated cleanly. Browser only;
 * call before the app starts.
 */
export function recordHydrationMismatches(): void {
    if (import.meta.env.SSR || typeof window === 'undefined') {
        return;
    }

    const mismatches: string[] = [];
    (
        window as unknown as { __hydrationMismatches: string[] }
    ).__hydrationMismatches = mismatches;

    for (const level of ['error', 'warn'] as const) {
        const original = console[level].bind(console);

        console[level] = (...args: unknown[]) => {
            const text = args.map((arg) => String(arg)).join(' ');

            if (/hydration/i.test(text)) {
                mismatches.push(text);
            }

            original(...args);
        };
    }
}
