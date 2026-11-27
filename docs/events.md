# Events

Plan get-togethers per server: who's coming, food, rides, and (in later slices) costs and Discord announcements.

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
- `event_rides` (driver, direction `to`/`from`/`both`, departs_from, departs_at, seats, note) and `event_ride_passengers` (one per person and ride: pickup_note).
