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
- **participate**: view, and the event isn't cancelled.
- **answer** (`canAnswer`): participate, and before `rsvp_by`, except that Can't is always allowed and organisers may still answer. Checked in `RsvpRequest::after()` (a validation error, not a 403) and in the Discord button handler.
- Load `hosts` before calling `canEdit` in a loop.

## Date vote

- `events.date_polling` means the date isn't decided: `starts_at` holds the earliest `event_date_options` row (kept in sync by `AddDateOption` / `RemoveDateOption`) and is never edited through the event form (`UpdateEvent` skips start and end). Anything that needs a real date (reminders, the native Discord event, RSVP buttons, going/maybe answers) checks `date_polling` first.
- `ChooseDate` is the only way out of a vote: it sets the date and turns yes/if-need-be votes into going/maybe RSVPs for people who hadn't answered.

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
- Organiser-only data (dietary needs, who answered which question) is `null` for everyone else, never just hidden in the UI.
- Question answers are saved with the RSVP (`answers` in `RsvpRequest`, `SaveAnswers`), only while going or maybe. A value a question doesn't accept clears the answer instead of failing. Counts only include people going or maybe.
- Anything that can run out (ride seats) is checked inside a transaction with `lockForUpdate()`, and the action throws a domain exception (`RideFullException`) that the controller turns into an error toast.
- Costs: parse typed amounts with `Planning\Money::toMinor()` (accepts `120`, `120.5`, `120,50`), split with `Money::splitEqually()` (shares always add up), and settle with `Planning\SettleUp`. Balances come from `CostsPlan` on every read. Payments change only the balance, never "paid" or "share".
- Costs are managed with the `manageCosts` ability (view access), not `participate`, so a cancelled event can still be settled.

## Discord

- Everything Discord-side goes through `SyncEventToDiscord` (announcement + native event), dispatched by the actions: `CreateEvent`/`UpdateEvent`/`CancelEvent` with details, `RecordRsvp` with `details: false` (counts only). It's `ShouldBeUniqueUntilProcessing`, and a Discord 4xx (missing permission, deleted channel) is logged, never retried.
- Message bodies come from the pure `Planning\Discord\EventAnnouncement`. RSVP buttons use `Discord\Interactions\EventCustomId` (`event:rsvp:<id>:<status>`), are acknowledged privately, and are handled by `HandleEventInteraction`, which keeps guests, note and ride flag from the website.
- Reminders mention people only when their DMs are closed, with `allowed_mentions.users` listing exactly them.
