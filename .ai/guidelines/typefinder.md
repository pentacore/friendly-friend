# friendly-friend: TypeScript types (Typefinder and Wayfinder)

- **Never hand-write TypeScript types for backend data.** Typefinder generates them into `resources/js/typefinder/`, covering models, enums (`as const` objects plus unions), form requests, API resources, Inertia page props and broadcast events. Import with `import type { … } from '@/typefinder'` (or a category barrel such as `@/typefinder/models`).
- The output is **committed**, and CI fails on drift. Run `vendor/bin/sail artisan typefinder:generate` after backend changes and commit the result.
- Inertia actions carry `#[TypefinderPage(component: …, props: [...])]`. Rector's `AddTypefinderPageRector` adds them when the props are a literal array, so prefer literal prop arrays in `Inertia::render()`. Replace any `'unknown'` prop type that Rector writes with the real type.
- Shared props are documented with `@typefinder-return array{…}` on `HandleInertiaRequests::share()` (kept in sync by `SyncSharedPropsShapeRector`). Write classes fully qualified in that tag.
- In pages, combine the shared props with the page's own props: `usePage<Omit<SharedPageProps, keyof PageProps[N]> & PageProps[N]>()`.
- Routes and controller actions on the frontend come from **Wayfinder** (`@/actions/...`, `@/routes/...`). Never hard-code URLs.
- Use `#[TypefinderIgnore]` only for classes that must never reach the frontend.
- The Typefinder package is maintained by the project owner. Report generator bugs or rough edges to them instead of quietly working around them.
