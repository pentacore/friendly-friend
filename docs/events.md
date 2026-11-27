# Events

Plan get-togethers per server: who's coming, and (in later slices) food, rides, costs and Discord announcements.

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

## Live updates

Every change broadcasts `event.changed` (`{ event_id, section }`) on the private channel `events.{id}`. Everyone viewing the event reloads just the changed part of the page. Channel access follows the event's server.

## Schema

- `events`: guild, creator, title, description (plain text), location, location_url, starts_at, ends_at, timezone, status (`planned`/`cancelled`), currency (`SEK`).
- `event_hosts`: co-hosts.
- `event_rsvps`: one per person and event: status (`going`/`maybe`/`declined`), guests, note, needs_ride.
