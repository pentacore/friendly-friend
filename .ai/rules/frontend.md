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
- Resources are unwrapped app-wide (`JsonResource::withoutWrapping()`), so pass resource objects as props, not `->resolve()`. Paginated feeds use `Inertia::scroll(Resource::collection($paginator))`, typed `SimplePaginatedResourceCollection<…>`, with `<InfiniteScroll data="…">`. Reset them on filter changes (`reset: ['quotes']`).
- Routes and actions come from Wayfinder (`@/routes/...`, `@/actions/...`). Never hard-code URLs.
- Layout props that depend on page props (breadcrumbs) use `setLayoutProps()` in `<script setup>`. The function form of `defineOptions({ layout })` gets no page props.
- **Dev server trap:** `defineProps<PageProps['x']>()` is resolved through Vue's type cache. The `refreshVueTypesOnTypefinderChange` plugin in `vite.config.ts` invalidates it when Typefinder writes. Without it, pages silently compile with **no props**. If props are ever undefined in dev, restart `npm run dev`.

## Live updates

- Broadcast events are typed by Typefinder (`BroadcastEvents['…']`). Declare payloads with `#[TypefinderBroadcast(payload: [...])]` (TS types), because `broadcastWith()` can't be evaluated statically.
- A page subscribes **once** per channel (e.g. `provideLiveQuoteScores()` with `useEcho`) and shares the values through `provide`/`inject`. Components never open their own subscriptions per item.
- Interactions that shouldn't reload the page (votes) use `useHttp` with an optimistic local update and a rollback on failure. The endpoint answers with a resource.

## Design

- Theme tokens only (`bg-background`, `text-muted-foreground`, `border`, `bg-card`…). Never use fixed colours, so light and dark both work. Default appearance is "system".
- **Quote identity:** quote text uses `font-quote` (Bricolage Grotesque). The signature is a highlighter swipe behind the opening quote mark (`bg-highlight`, separate light and dark tokens in `app.css`), with the mark itself `text-black`. Use `components/quotes/QuoteText.vue` for any quote; don't restyle quotes per page.
- UI text is Instrument Sans. Keep everything around the quote quiet: the highlighter is the one bold element.
- Copy is plain and specific ("Save changes", "Remove quote"). Empty states tell you how to get content ("right-click a message → Apps → Save quote").

## Checking

- Every page gets a Pest browser test in `tests/Browser`, run in light **and** dark mode: no JavaScript errors, content visible, and a screenshot after `document.fonts.ready`. Run `npm run build` then `composer test:browser`. Look at the screenshots.
