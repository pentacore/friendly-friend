---
paths:
    - 'app/Planning/**'
    - 'app/Models/Event*.php'
    - 'app/Http/Controllers/Event*.php'
    - 'app/Http/Requests/Events/**'
    - 'resources/js/pages/events/**'
    - 'resources/js/components/events/**'
---

# Event planning

## Shape

- The domain lives in `App\Planning` (actions, `EventRules`, Discord flows, jobs). The model is `App\Models\Event` (table `events`). `App\Events` is only for broadcast classes, so don't put planning code there.
- Every person in an event (RSVP, host, claim, cook, driver, passenger, expense share) is a `discord_users` row. Show people with `DiscordUser::toMember()` (`{ id, name, avatar_url }`).

## Permissions (`EventRules`, used by `EventPolicy` and `EventResource`)

- **view**: access through the event's server.
- **edit**: the creator, co-hosts (`event_hosts`, only current members of that server) and the server's admins.
- **participate**: view, and the event isn't cancelled. Costs stay open on cancelled events so they can be settled.
- Load `hosts` before calling `canEdit` in a loop.

## Times and money

- `starts_at` and `ends_at` are `timestamptz`. Forms send local `Y-m-d\TH:i` in the event's `timezone` (`SaveEventRequest::details()` converts). The edit form gets local strings back. The frontend formats with `lib/eventDates.ts` in the event's timezone, never the browser's.
- Without `ends_at`, an event counts as "coming up" until 6 hours after it starts.
- Money is integer minor units (öre) in the event's `currency`. Balances are computed, never stored.

## Changes and live updates

- Every write goes through an action, which ends with `broadcast(new EventChanged($event->id, <section>))`. The section is `overview`, `food`, `transport` or `costs`. The event page (`useLiveEvent`) reloads only that section's props.
- Writes from the page are small FormRequest + action + redirect back. No JSON endpoints.
- Creating an event RSVPs the creator as going.

## Tabs

- The event page has one tab per section (Overview, Food, …). A section's data is one lazy prop (`food` from `FoodPlan::for()`), and its writes reload only that prop (`useEventAction(['food'])`). Child routes use `scopeBindings()`, so an item of another event is a 404.
- Organiser-only data (dietary needs) is `null` for everyone else, never just hidden in the UI.
