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
- Every Inertia action has `#[TypefinderPage(component: …, props: [...])]`. Prop types are **TypeScript** strings (`'{ id: number; name: string }[]'`), never PHPDoc shapes. Resource class strings become the resource type.
- Inside a prop type string, reference enums and resources by class (`'{ status: '.RsvpStatus::class.' }'`). A bare TS name like `RsvpStatus` isn't imported into `pages.d.ts` and breaks the type check.
- Form requests' `rules()` must work without a route or user: Typefinder evaluates them statically (`--strict` fails on the warning). Read route models with `$this->route('event') instanceof Event ? … : []`, never a helper that aborts. Don't branch on input either (`$this->input('kind') === …`): express conditional rules with `exclude_unless`, `required_if` and similar, so Typefinder sees one shape.
- Resources are unwrapped app-wide (`JsonResource::withoutWrapping()`), so pass resource objects as props, not `->resolve()`. Paginated feeds use `Inertia::scroll(Resource::collection($paginator))`, typed `SimplePaginatedResourceCollection<…>`, with `<InfiniteScroll data="…">`. Reset them on filter changes (`reset: ['quotes']`).
- Routes and actions come from Wayfinder (`@/routes/...`, `@/actions/...`). Never hard-code URLs.
- Layout props that depend on page props (breadcrumbs) use `setLayoutProps()` in `<script setup>`. The function form of `defineOptions({ layout })` gets no page props.
- **Dev server trap:** `defineProps<PageProps['x']>()` is resolved through Vue's type cache. The `refreshVueTypesOnTypefinderChange` plugin in `vite.config.ts` invalidates it when Typefinder writes. Without it, pages silently compile with **no props**. If props are ever undefined in dev, restart `npm run dev`.

## Live updates

- Broadcast events are typed by Typefinder (`BroadcastEvents['…']`). Declare payloads with `#[TypefinderBroadcast(payload: [...])]` (TS types), because `broadcastWith()` can't be evaluated statically.
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
