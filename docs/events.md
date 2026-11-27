# Events

Plan get-togethers per server: who's coming, food, rides, costs, and announcements with RSVP buttons and reminders in Discord.

## Who can do what

| Who                                   | Can                                                |
| ------------------------------------- | -------------------------------------------------- |
| Anyone with access through the server | See the event, create events, RSVP                 |
| The creator, co-hosts, server admins  | Edit the event, choose co-hosts, cancel or restore |

Cancelled events stay listed (with a "Cancelled" badge). Nobody can RSVP to them, but their costs can still be settled.

## Pages

- **`/events`** (sidebar → Together → Events): coming up (soonest first) and "been and gone" (latest 30), filterable by server.
- **`/events/create`**: server, title, start and optional end (in the event's timezone, default `STATS_TIMEZONE`), place and map link, details, co-hosts (current members of that server). The creator is automatically going.
- **`/events/{id}`**: date, place, hosts, details, and who's going, maybe or can't make it, with guests, notes and who needs a ride. Your own answer (Going / Maybe / Can't, guests, a note, "I need a ride") is saved straight away.
- **`/events/{id}/edit`**: the same form, for editors.

## Food tab

- **Dietary needs** (organisers only): the notes of everyone going or maybe going. People write them once in **Settings → Food** (`/settings/food`, `discord_users.dietary_notes`).
- **Bring list:** organisers add items with how many are needed (6 chairs). Anyone claims some (`- 1 +`), and the list shows "5 / 6" and who brings what.
- **Shopping list:** anyone adds items with an amount. Tick when bought; it shows who got it, and bought items sink to the bottom.
- **Meal plan:** organisers add meals by day and slot (breakfast, lunch, dinner, snack). Anyone volunteers to cook ("I'll cook").

## Transport tab

- **Still needs a ride there:** everyone going or maybe who ticked "I need a ride" and isn't yet in a ride that goes there (`to` or `both`).
- **Rides**, grouped as There, Back, There and back: the driver, where and when they leave (in the event's timezone), a note, and the seats as a bar with "N free".
- **Take a seat** with an optional pickup note. The ride is locked while seating, so two people can't take the last seat. The driver can't sit in their own ride. Leave with ×; the driver and organisers can remove passengers and cancel the ride.
- **Offer a ride:** which way, free seats (1 to 8), leaving from, leaving at, note. To change a ride, cancel it and offer a new one.

## Costs tab

- **Expenses:** what, how much (type `450` or `120,50`), who paid, and who shares it: **equally** (leftover öre go to the first people, so it always adds up) or **exact amounts** (they must add up to the total). The people to choose from are everyone going or maybe, the hosts, and anyone already in the costs.
- **Settle up:** everyone's balance (paid minus share, adjusted by payments) and the transfers that square it: the biggest debtor pays the biggest creditor, repeated. **Mark as paid** records the payment; anyone can do it, and the person who recorded it (or an organiser) can undo it.
- **Balances** table: paid, share and balance per person.
- Money is stored as integer minor units in the event's currency (`SEK`).

## Discord

Set **Announce events in** for a server on the Servers page (`guilds.events_channel_id`). Then:

- **Announcement:** a new event is posted there as an embed (when, where, who's coming, a link to the page) with **Going / Maybe / Can't** buttons. It's edited whenever the details or the counts change, and shows "Cancelled:" with disabled buttons when called off. Posting is done by `Planning\Jobs\SyncEventToDiscord`, unique while queued, so a burst of RSVPs edits once.
- **Buttons** are answered privately ("You're going to …") and keep the guests, note and ride flag set on the website. Only members with access through that server can answer.
- **Native Discord event:** the event is mirrored as an external scheduled event (location, start, end; 6 hours if no end), updated on edits and cancelled with the event. One-way: Discord's "Interested" isn't read back. Needs **Create Events / Manage Events**; without it, this step is skipped and logged.
- **Reminders** (`SendEventReminders`, every 5 minutes): a DM to everyone going, about 24 hours and 2 hours before. Each is sent once; an event planned at short notice only gets the 2-hour one. People with closed DMs are mentioned in the events channel instead, the one deliberate ping the bot makes.

## Live updates

Every change broadcasts `event.changed` (`{ event_id, section }`) on the private channel `events.{id}`. Everyone viewing the event reloads just the changed part of the page. Channel access follows the event's server.

## Schema

- `events`: guild, creator, title, description (plain text), location, location_url, starts_at, ends_at, timezone, status (`planned`/`cancelled`), currency (`SEK`).
- `event_hosts`: co-hosts.
- `event_rsvps`: one per person and event: status (`going`/`maybe`/`declined`), guests, note, needs_ride.
- `event_bring_items` (label, quantity_needed) and `event_bring_claims` (one per person and item: quantity, note).
- `event_shopping_items`: label, quantity (free text), added_by, checked_by, checked_at.
- `event_meals` (date, slot, title, notes) and `event_meal_cooks`.
- `discord_users.dietary_notes`.
- `event_expenses` (paid_by, added_by, description, amount, split_mode `equal`/`exact`), `event_expense_shares` (person, amount) and `event_payments` (from, to, recorded_by, amount, settled_at).
- Discord: `guilds.events_channel_id`, and on `events`: announce_channel_id, discord_message_id, discord_scheduled_event_id, reminded_day_at, reminded_hours_at.
- `event_rides` (driver, direction `to`/`from`/`both`, departs_from, departs_at, seats, note) and `event_ride_passengers` (one per person and ride: pickup_note).
