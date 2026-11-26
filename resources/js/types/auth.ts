import type { SharedPageProps } from '@/typefinder';

/** Shared `auth` prop (see HandleInertiaRequests::share()). */
export type Auth = SharedPageProps['auth'];

/** The logged-in user: Discord identity only, no email or password. */
export type User = NonNullable<Auth['user']>;
