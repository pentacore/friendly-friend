# Events

Plan get-togethers per server: who's coming, food, rides, costs, and announcements with RSVP buttons and reminders in Discord.

## Who can do what

| Who                                   | Can                                                |
| ------------------------------------- | -------------------------------------------------- |
| Anyone with access through the server | See the event, create events, RSVP                 |
| The creator, co-hosts, server admins  | Edit the event, choose co-hosts, cancel or restore |

Cancelled events stay listed (with a "Cancelled" badge). Nobody can RSVP to them, but their costs can still be settled.

## Pages

- **`/events`** (sidebar → Together → Events): coming up (soonest first) and "been and gone" (newest first, 20 at a time with infinite scroll), filterable by server and searchable by name or place (`EventIndexRequest`, `ilike` with `%`/`_` taken literally).
- **`/events/create`**: server, title, start and optional end (in the event's timezone, default `STATS_TIMEZONE`), place and map link, details, co-hosts (current members of that server, with a filter when there are more than six). The creator is automatically going. Times can't be in the past; a past event can still be edited as long as its date isn't changed.
    - **Plan ahead (optional):** questions, a bring list, shopping, meals, your own ride, and costs you paid. Each section opens with a button and can be removed again (×, or by removing its last row); rows left blank are skipped. Costs added here are split among **everyone going**.
- **`/events/{id}`**: date, place, hosts, details, and who's going, maybe or can't make it, with guests, notes and who needs a ride. Your own answer (Going / Maybe / Can't, guests, a note, "I need a ride") is saved straight away.
- **`/events/{id}/edit`**: the same form, for editors.
- **Add to calendar** (`/events/{id}/calendar.ics`): the event as an iCalendar file (UTC times, 6 hours when there's no end, cancelled events marked as such). Not while the date is being voted on.

## Voting on the date

Not sure when? Tick **Let people vote on the date** on the new-event form and propose 2 to 10 dates instead of one: a day, a start time, and optionally an end time and a later end day (for trips over several days). New dates take the first date's times and length until you change them.

- While the vote is open the event shows "Date to be decided" (a **?** badge), and the "Are you coming?" box is replaced by **Which dates work for you?**: everyone answers each date **Yes / If need be / No** (press again to take it back), and sees the tallies and who said what. The date with the most yes (then most if need be) is marked **Best so far**.
- Organisers can **propose another date**, withdraw one (not the last), and **Choose this date**. Choosing sets the event's time, and everyone who voted yes on it becomes **going**, if need be becomes **maybe**, unless they had already answered. Everyone who voted (except whoever chose) gets a DM with the date and where they stand (`NotifyDateChosen`).
- Until a date is chosen: only "Can't" is accepted as an RSVP, no native Discord event is created, and no reminders go out. Editing the event keeps the date out of the form.
- **In Discord**, the announcement lists the proposed dates with their tallies and a **Vote on dates** button instead of the RSVP buttons. It opens a private picker with two dropdowns, "Dates I can make" and "Dates that work if need be", pre-filled with your votes. Each change saves at once; dates you pick in neither count as no. The tallies on the announcement update as people vote. Once a date is chosen, the RSVP buttons replace the vote button.
- `starts_at` holds the earliest proposed date while voting, so lists still sort sensibly.

## Answer by

An event can have an **Answer by** time (in the event's timezone, no later than the start).

- Before it, any answer goes. After it, Going and Maybe close and only **Can't** is accepted: dropping out is always possible. Organisers can still change their own answer.
- The RSVP box says "Please answer by …", the events list shows "Answer by …" to anyone who hasn't answered or is on Maybe, and the Discord announcement shows it as a field.
- A day before it, everyone on **Maybe** gets a DM asking them to decide (once).
- When it passes, the announcement is refreshed once, so Going and Maybe grey out; Discord answers say answers are closed.

## Questions

Organisers can ask everyone coming something, on the Overview tab:

- **Yes or no** ("Will you eat crayfish?"), **Pick one** (two to twelve options: "Which sauna slot?") or **Free text** ("Anything we should know?").
- People answer in the "Are you coming?" box once they're going or maybe; answers save straight away and can be cleared by pressing the chosen one again.
- **Everyone** sees the counts (only people going or maybe count). **Organisers** also see who answered what, and all free-text answers.
- Organisers reorder questions with the ↑/↓ buttons (`MoveQuestion`, which renumbers `position` 1..n). - Removing a question deletes its answers. The Discord RSVP reply mentions when there are questions to answer on the website.

## Food tab

- **Dietary needs** (organisers only): the notes of everyone going or maybe going. People write them once in **Settings → Food** (`/settings/food`, `discord_users.dietary_notes`).
- **Bring list:** organisers add items with how many are needed (6 chairs). Anyone claims some (`- 1 +`), and the list shows "5 / 6" and who brings what.
- **Shopping list:** anyone adds items with an amount. Tick when bought; it shows who got it, and bought items sink to the bottom. An expense can say which items it **paid for** (a checklist in the expense form, `CoverShoppingItems`); the item then shows "paid in …" and the expense "Paid for: …". An item belongs to one expense at most; removing the expense unlinks it.
- **Meal plan:** organisers add meals by day and slot (breakfast, lunch, dinner, snack). Anyone volunteers to cook ("I'll cook").

## Transport tab

- **Still needs a ride there:** everyone going or maybe who ticked "I need a ride" and isn't yet in a ride that goes there (`to` or `both`).
- **Rides**, grouped as There, Back, There and back: the driver, where and when they leave (in the event's timezone), a note, and the seats as a bar with "N free".
- **Take a seat** with an optional pickup note. The ride is locked while seating, so two people can't take the last seat. The driver can't sit in their own ride. Leave with ×; the driver and organisers can remove passengers and cancel the ride.
- **Offer a ride:** which way, free seats (1 to 8), leaving from, leaving at, note. The driver or an organiser can **edit** a ride (not below the seats already taken).

## Costs tab

- **Expenses:** what, how much (type `450` or `120,50`), who paid, and who shares it: **equally** (leftover öre go to the first people, so it always adds up), **exact amounts** (they must add up to the total), or **everyone going**: worked out whenever the costs are shown, from who's going right now, with each guest as an extra share (if nobody is going, the payer carries it). The people to choose from are everyone going or maybe, the hosts, and anyone already in the costs.
- **Settle up:** everyone's balance (paid minus share, adjusted by payments) and the transfers that square it: the biggest debtor pays the biggest creditor, repeated. Expenses can be edited (pencil) by whoever added them, whoever paid, or an organiser. **Mark as paid** records the payment; anyone can do it, and the person who recorded it (or an organiser) can undo it.
- **Balances** table: paid, share and balance per person.
- Money is stored as integer minor units in the event's currency, picked on the event form (**Currency for costs**: SEK by default, or NOK, DKK, EUR, GBP, USD; enum `App\Enums\Currency`). It can only change while the event has no expenses or payments (`Event::hasCosts()`), since stored amounts would otherwise mean something else.

## Discord

Set **Announce events in** for a server on the Servers page (`guilds.events_channel_id`). Then:

- **Announcement:** a new event is posted there as an embed (when, where, who's coming, a link to the page) with **Going / Maybe / Can't** buttons. It's edited whenever the details or the counts change, and shows "Cancelled:" with disabled buttons when called off. Posting is done by `Planning\Jobs\SyncEventToDiscord`, unique while queued, so a burst of RSVPs edits once.
- **Buttons** are answered privately ("You're going to …") and keep the guests, note and ride flag set on the website. Only members with access through that server can answer.
- **Native Discord event:** the event is mirrored as an external scheduled event (linked from the event page as **Discord event**, next to **Announcement in Discord**) (location, start, end; 6 hours if no end), updated on edits and cancelled with the event. Discord doesn't let bots mark people as "Interested", so that count only covers people who pressed it in Discord. The event's description therefore starts with the real numbers ("4 going (+1) · 1 maybe · 2 can't"), refreshed as people answer. Pressing **Interested** in Discord makes you **going** (unless you already answered; ignored after the answer-by time and while the date is undecided), and taking it back removes an RSVP that only "Interested" made. Needs **Create Events / Manage Events**; without it, this step is skipped and logged.
- **Reminders** (`SendEventReminders`, every 5 minutes): a DM to everyone going, about 24 hours and 2 hours before. Each is sent once; an event planned at short notice only gets the 2-hour one. People with closed DMs are mentioned in the events channel instead, the one deliberate ping the bot makes.

## Live updates

Every change broadcasts `event.changed` (`{ event_id, section }`) on the private channel `events.{id}`. Everyone viewing the event reloads just the changed part of the page. Channel access follows the event's server.

## Schema

- `events`: guild, creator, title, description (plain text), location, location_url, starts_at, ends_at, timezone, status (`planned`/`cancelled`), currency (`App\Enums\Currency`, default `SEK`).
- `event_hosts`: co-hosts.
- `event_rsvps`: one per person and event: status (`going`/`maybe`/`declined`), guests, note, needs_ride.
- `event_bring_items` (label, quantity_needed) and `event_bring_claims` (one per person and item: quantity, note).
- `event_shopping_items`: label, quantity (free text), added_by, checked_by, checked_at, expense_id (the expense that paid for it, null on delete).
- `event_meals` (date, slot, title, notes) and `event_meal_cooks`.
- `discord_users.dietary_notes`.
- `event_expenses` (paid_by, added_by, description, amount, split_mode `equal`/`exact`), `event_expense_shares` (person, amount) and `event_payments` (from, to, recorded_by, amount, settled_at).
- `events.date_polling`, `event_date_options` (starts_at, ends_at) and `event_date_votes` (one per person and date: answer `yes`/`if_need_be`/`no`).
- `event_questions` (position, prompt, kind `yes_no`/`choice`/`text`, options) and `event_question_answers` (one per person and question: value).
- `events.rsvp_by` (answer by), `reminded_rsvp_at` (Maybe nudge sent), `rsvp_closed_synced_at` (announcement refreshed after the deadline).
- Discord: `guilds.events_channel_id`, and on `events`: announce_channel_id, discord_message_id, discord_scheduled_event_id, reminded_day_at, reminded_hours_at.
- `event_rides` (driver, direction `to`/`from`/`both`, departs_from, departs_at, seats, note) and `event_ride_passengers` (one per person and ride: pickup_note).
