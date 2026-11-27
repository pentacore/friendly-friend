# Phase 7: Event Planning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Friends plan get-togethers on the website, per server, and the plan doesn't stop at the date. It covers who's coming, who brings what, what to buy, who cooks, who drives, and who owes whom. Discord announces each event with RSVP buttons, mirrors it as a native server event and sends reminders.

**Architecture:**

- **Domain:** `App\Planning` holds the actions, `EventRules` (shared by `EventPolicy` and the resources), the Discord flows and jobs. The model is `App\Models\Event` on the `events` table. Broadcast classes stay in `App\Events`.
- **Who sees an event:** users with access through the event's server (`AccessSnapshot::canSeeGuild`).
- **Who edits it:** the creator, co-hosts (`event_hosts`) and that server's admins.
- **Who takes part:** anyone who can see the event, while it isn't cancelled. They RSVP, claim and tick items, offer and join rides, and add expenses.
- **People are Discord accounts.** RSVPs, claims, cooks, drivers, passengers and expense shares all point to `discord_users`.
- **Times:** stored as `timestamptz`, entered and shown in the event's `timezone`, which defaults to `config('stats.timezone')`. The forms send local `Y-m-d\TH:i`.
- **Money:** integers in minor units (öre). The currency is per event (`SEK`). Balances are computed, never stored. Settle-up pairs the largest debtor with the largest creditor, greedily.
- **Live updates:** every change broadcasts `EventChanged {event_id, section}` on the private channel `events.{id}`. The page reloads only the props of that section (`router.reload({ only })`).
- **Website:**
    - `/events` lists upcoming and past events, with a server filter.
    - `/events/create` and `/events/{event}/edit` hold the event form.
    - `/events/{event}` has the tabs Overview, Food, Transport and Costs.
    - Every write is a small FormRequest + action + redirect back.
- **Discord:**
    - `guilds.events_channel_id` is set on the Servers page.
    - `SyncEventToDiscord` posts or edits the announcement (an embed with Going / Maybe / Can't buttons and counts) and creates, edits or cancels the native scheduled event.
    - Button presses (`event:rsvp:<id>:<status>`) are acknowledged privately and handled by `HandleEventInteraction`.
    - `SendEventReminders` runs every 5 minutes and DMs `going` attendees 24 h and 2 h before. If someone's DMs are closed, it mentions them in the events channel instead.

## Slices (each a commit, with tests, rules, docs and ROADMAP)

1. **Core and RSVP:**
    - `events`, `event_hosts`, `event_rsvps`
    - the enums `EventStatus` and `RsvpStatus`
    - `EventRules` and the policy
    - the actions create, update, cancel and RSVP
    - the pages index, create, edit and show (Overview tab)
    - the `EventChanged` broadcast and its channel
    - the sidebar link
2. **Food:**
    - `discord_users.dietary_notes` and `/settings/food`
    - a dietary summary for organisers
    - the bring list with claims
    - the shopping list with ticks
    - the meal plan with cooks
3. **Transport:**
    - rides (driver, direction, from, when, seats)
    - joining and leaving rides, with a pickup note
    - a "needs a ride" list from the RSVPs
4. **Costs:**
    - expenses with equal or exact splits
    - payments and balances
    - settle-up suggestions with "mark as paid"
5. **Discord:**
    - `events_channel_id`
    - the announcement with RSVP buttons, and handling them
    - the scheduled-event mirror
    - reminders
6. **Polish:** browser tests in light and dark mode for every tab, docs, ROADMAP and push.
