/**
 * Highlighting search matches the way `Quote::search()` finds them: each
 * word of the search (not the `-excluded` ones) matches words starting with
 * it, ignoring case and accents. Close matches (typos) aren't highlighted.
 */

/** Lowercase without accents: "Håkan" → "hakan", like the server's `f_unaccent`. */
function fold(text: string): string {
    return text.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase();
}

/** The search's words, folded, as the server splits them. */
export function searchWords(query: string): string[] {
    return query
        .trim()
        .split(/\s+/u)
        .filter((word) => word !== '' && !word.startsWith('-'))
        .flatMap((word) => word.split(/[^\p{L}\p{N}]+/u))
        .filter((part) => part !== '')
        .map(fold);
}

/** Text split into parts, with the words a search matched flagged. */
export function highlightParts(
    text: string,
    words: string[],
): { text: string; match: boolean }[] {
    if (words.length === 0) {
        return [{ text, match: false }];
    }

    const parts: { text: string; match: boolean }[] = [];
    let last = 0;

    for (const found of text.matchAll(/[\p{L}\p{N}]+/gu)) {
        const word = fold(found[0]);

        if (!words.some((prefix) => word.startsWith(prefix))) {
            continue;
        }

        const start = found.index ?? 0;

        if (start > last) {
            parts.push({ text: text.slice(last, start), match: false });
        }

        parts.push({ text: found[0], match: true });
        last = start + found[0].length;
    }

    if (last < text.length) {
        parts.push({ text: text.slice(last), match: false });
    }

    return parts;
}
