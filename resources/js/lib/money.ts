import { displayLocale } from '@/lib/locale';

/** Minor units (öre) as "1 234,50 kr" in the viewer's locale. */
export function formatMoney(minor: number, currency: string): string {
    return new Intl.NumberFormat(displayLocale(), {
        style: 'currency',
        currency,
    }).format(minor / 100);
}
