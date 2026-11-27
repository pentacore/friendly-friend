import type { SaveEventRequest } from '@/typefinder';

const filled = (value: string | null | undefined) =>
    (value ?? '').trim() !== '';

/**
 * Drops untouched rows from the "Plan ahead" sections before saving, so a
 * section someone opened but left empty doesn't block the form.
 */
export function withoutBlankRows<T extends SaveEventRequest>(data: T): T {
    const keep = <Row>(
        rows: Row[] | null | undefined,
        isFilled: (row: Row) => boolean,
    ): Row[] | null => {
        const kept = (rows ?? []).filter(isFilled);

        return kept.length > 0 ? kept : null;
    };

    return {
        ...data,
        questions: keep(data.questions, (q) => filled(q.prompt)),
        bring: keep(data.bring, (item) => filled(item.label)),
        shopping: keep(data.shopping, (item) => filled(item.label)),
        meals: keep(data.meals, (meal) => filled(meal.title)),
        costs: keep(
            data.costs,
            (cost) => filled(cost.description) || filled(cost.amount),
        ),
    };
}
