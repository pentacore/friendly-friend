---
paths:
    - 'resources/js/**'
    - 'resources/css/**'
    - 'resources/views/**'
    - 'app/Http/Controllers/**'
    - 'app/Http/Resources/**'
    - 'app/Http/Requests/**'
    - 'vite.config.ts'
---

# Frontend

## Types and routes

- **Never hand-write TypeScript types for backend data.** Typefinder generates them into `resources/js/typefinder/` (models, enums, requests, resources, page props, broadcast events). Import from `@/typefinder`. The output is committed, and CI fails on drift.
- Every Inertia action has `#[TypefinderPage(component: …, props: [...])]`. Prop types are **TypeScript** strings (`'{ id: number; name: string }[]'`) by convention. Typefinder 8.6+ also converts PHPDoc types (`array{id: int}`, `list<User>`), and a type mixing both or one it can't express fails `--strict`. Resource class strings become the resource type.
- Inside a prop type string, reference enums and resources by class (`'{ status: '.RsvpStatus::class.' }'`). A bare TS name like `RsvpStatus` isn't imported into `pages.d.ts` and breaks the type check.
- Form requests' `rules()` must work without a route or user: Typefinder evaluates them statically (`--strict` fails on the warning). Read route models with `$this->route('event') instanceof Event ? … : []`, never a helper that aborts. Don't branch on input either (`$this->input('kind') === …`): express conditional rules with `exclude_unless`, `required_if` and similar, so Typefinder sees one shape.
- Resources are unwrapped app-wide (`JsonResource::withoutWrapping()`), so pass resource objects as props, not `->resolve()`. Paginated feeds use `Inertia::scroll(Resource::collection($paginator))`, typed `SimplePaginatedResourceCollection<…>`, with `<InfiniteScroll data="…">`. Reset them on filter changes (`reset: ['quotes']`).
- Routes and actions come from Wayfinder (`@/routes/...`, `@/actions/...`). Never hard-code URLs.
- Layout props that depend on page props (breadcrumbs) use `setLayoutProps()` in `<script setup>`. The function form of `defineOptions({ layout })` gets no page props.
- **Dev server trap:** `defineProps<PageProps['x']>()` is resolved through Vue's type cache. Typefinder's Vite plugin (8.6+) invalidates it when it regenerates; don't add a local plugin for it. If props are ever undefined in dev anyway, restart `npm run dev`.

## Live updates

- Broadcast events are typed by Typefinder (`BroadcastEvents['…']`). Declare payloads as a PHPDoc `@return array{…}` shape on `broadcastWith()` (literal unions like `'food'|'costs'` carry over); Typefinder 8.6+ reads it, so no `#[TypefinderBroadcast]` attribute is needed. Narrow the promoted constructor property with `@param` so Larastan accepts the shape.
- A page subscribes **once** per channel (e.g. `provideLiveQuoteScores()` with `useEcho`) and shares the values through `provide`/`inject`. Components never open their own subscriptions per item.
- Admin-only progress that changes rarely (imports) polls with `usePoll(…, { only: [...] })` while something is running, instead of adding a broadcast channel.
- Interactions that shouldn't reload the page (votes) use `useHttp` with an optimistic local update and a rollback on failure. The endpoint answers with a resource.

## Naming

- Don't name page props after JavaScript keywords (`import`, `default`, `class`): templates can't use them. Use `quote_import` and so on.

## Design

- Theme tokens only (`bg-background`, `text-muted-foreground`, `border`, `bg-card`…). Never use fixed colours, so light and dark both work. Default appearance is "system".
- **Quote identity:** quote text uses `font-quote` (Bricolage Grotesque). The signature is a highlighter swipe behind the opening quote mark (`bg-highlight`, separate light and dark tokens in `app.css`), with the mark itself `text-black`. Use `components/quotes/QuoteText.vue` for any quote; don't restyle quotes per page.
- The logo (`AppLogoIcon`) is the same highlighted quote mark: black glyph on the highlighter swipe, with its own fills (works on any background).
- Icons are generated from `resources/icons/mark.svg` (favicon) and `resources/icons/app-icon.svg` (touch icons, web manifest, Discord avatar) by `vendor/bin/sail node scripts/render-icons.mjs`. Change the SVGs, rerun, and commit the files in `public/`. The Discord avatar and banner (`resources/icons/build/`, git-ignored; the banner is `resources/icons/banner.html` at 1200×480 with the quote font from Bunny Fonts, avatar-safe lower left) are uploaded in the developer portal.
- UI text is Instrument Sans. Keep everything around the quote quiet: the highlighter is the one bold element.
- Copy is plain and specific ("Save changes", "Remove quote"). Empty states tell you how to get content ("right-click a message → Apps → Save quote").

## Checking

- Every page gets a Pest browser test in `tests/Browser`, run in light **and** dark mode: no JavaScript errors, content visible, and a screenshot after `document.fonts.ready`. Run `npm run build` then `composer test:browser`. Look at the screenshots.

## Stats pages

- Boards use `components/stats/StatsBoard.vue` (ranked rows with a quiet relative bar) and `MemberAvatar` / `MemberName` (links to the person page when there is one). Numbers and durations go through `lib/statsFormat.ts`.
- The heatmap (`ActivityHeatmap.vue`) is the stats pages' one bold element: highlighter ink (`bg-highlight`), opacity scaled by the square root of the count, on `bg-muted` squares. It must fit a phone without scrolling.

## Navigation

- The sidebar (`AppSidebar.vue`) is grouped by what you came to do: Dashboard, then **Quotes**, **Together** (events, stats), and **Admin** (only shown to admins and owners). New pages go into one of these groups, not a flat list. `NavMain` takes one group (`label`, `items`) and marks a link active on its sub-pages too.

## Server-side rendering (same output on server and browser)

- Pages render on the server (Vite dev SSR, or `inertia:start-ssr`) and hydrate in the browser, so the first render must be identical on both.
- **Format every date and number through `lib/locale.ts`** (`displayLocale()`, `displayTimeZone()`, `pageNow()`: shared props from `HandleInertiaRequests`). Never `undefined` locales, timezone-less date formatting of instants, `Date.now()` or `new Date()` for "now" in render code. Calendar dates (`YYYY-MM-DD`) are formatted at noon UTC with `timeZone: 'UTC'`.
- **No browser-only APIs in `setup()` or render** (`window`, `document`, `localStorage`, `location.hash`): read them in `onMounted` and start from a server-safe default.
- **Live updates are browser-only**: `useEcho` subscriptions are skipped when `import.meta.env.SSR` (Echo can't connect on the server, and a throw there makes Inertia silently fall back to client rendering for the whole page).
- `lib/hydrationCheck.ts` records Vue's hydration errors in `window.__hydrationMismatches`; `tests/Browser/HydrationBrowserTest.php` checks key pages with a Swedish, New York browser. Run it locally with `npm run build:ssr` and `php artisan inertia:start-ssr` (it skips without an SSR server; CI runs one).
