# Glossary

The words this project uses for its domain, and the words to avoid. Use them in code, UI copy, rules, docs, commit messages and pull requests, and update this file in the same commit when a change adds or renames a term (`.ai/rules/general.md`). The "Words to avoid" list is a draft for the owner to prune.

## People and access

| Term         | Meaning                                                                                      | Code name                                 |
| ------------ | -------------------------------------------------------------------------------------------- | ----------------------------------------- |
| Server       | A Discord server the bot is in. It starts disabled; an owner enables it on the Servers page. | `Guild`, `guilds`                         |
| Discord user | One Discord account, the same row across every server.                                       | `DiscordUser`                             |
| Member       | A Discord user's membership of one server: nickname, roles, whether they're still in it.     | `GuildMember`                             |
| User         | Someone who has logged in to the site (always through Discord).                              | `User`                                    |
| Owner        | A Discord user listed in `DISCORD_OWNER_IDS`. Manages servers and everything else.           | `manage-guilds` gate, `discord.owner_ids` |
| Admin        | A member with one of a server's admin roles. Gets the admin tools, but can't manage servers. | `admin` gate, `GuildAccess::isAdmin()`    |
| Person       | Anyone who can be quoted, a Discord user or not.                                             | `Person`                                  |
| Alias        | Another name a person is known by, used to match who said a quote.                           | `PersonAlias`, `ResolvePerson`            |

## Quotes

| Term            | Meaning                                                                                                                 | Code name                                  |
| --------------- | ----------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| Quote           | One or more lines someone said, saved with who said them and when.                                                      | `Quote`                                    |
| Quote line      | One line of a quote, with its speaker. A conversation is a quote with several lines.                                    | `QuoteLine`                                |
| Capture         | Turning a Discord message into a draft: a reply mention, the "Save quote" app command, `/quote` or a free-text mention. | `QuoteSource`, `CaptureFromMention`        |
| Draft           | A captured quote waiting in a private preview to be saved. Unsaved drafts expire.                                       | `QuoteStatus::Draft`, `PruneExpiredDrafts` |
| Pending review  | An imported quote waiting for an admin to approve it on the website.                                                    | `QuoteStatus::PendingReview`               |
| Removed         | A quote taken off the site. It can be restored, so it is never "deleted".                                               | `QuoteStatus::Removed`, `RestoreQuote`     |
| Channel import  | Scanning a channel's history for old quotes, which land in pending review.                                              | `QuoteImport`, `ImportChannelQuotes`       |
| Watched channel | A channel where every message that reads like a quote is picked up.                                                     | `QuoteSource::WatchedChannel`              |
| Cleanup         | The AI pass that tidies a captured message into quote lines with speakers.                                              | `QuoteNormalizer`, `QuoteDetector`         |
| Jev             | The cheap classifier (through OpenRouter) that sorts what an @mention wants and picks between similar names.            | `App\Ai\Jev`, `MentionIntent`              |
| Edit history    | Earlier versions of a quote's text.                                                                                     | `QuoteRevision`                            |
| Vote, score     | 👍/👎 on a quote; the score is their sum.                                                                               | `QuoteVote`                                |
| This or that    | Two quotes side by side; you pick the better one.                                                                       | `QuoteMatchup`                             |
| Top lists       | Quotes and people ranked by score and by this-or-that wins.                                                             |                                            |

## Events

| Term          | Meaning                                                                                               | Code name                                        |
| ------------- | ----------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| Event         | Something the group plans together on the site, posted to a server.                                   | `Event` (`App\Planning`)                         |
| Organiser     | The event's creator or a co-host. Organisers edit the event and may answer after answers close.       | `EventRules`                                     |
| Co-host       | A member the creator adds as an organiser.                                                            | `hosts`                                          |
| RSVP          | A member's answer: Going, Maybe or Declined.                                                          | `EventRsvp`, `RsvpStatus`                        |
| Answer by     | The time after which Going and Maybe close.                                                           | `rsvp_by`                                        |
| Date poll     | Votes on candidate dates (Yes, If need be, No) before the organisers pick one.                        | `EventDateOption`, `EventDateVote`               |
| Announcement  | The bot's message about the event in the events channel, refreshed as it changes.                     | `SyncEventToDiscord`                             |
| Discord event | The native Discord scheduled event the site mirrors the event to. "Interested" there becomes a Maybe. | `SyncEventToDiscord`, `SyncDiscordInterest`      |
| Reminder      | The DMs the bot sends 24 hours and 2 hours before an event.                                           | `SendEventReminders`                             |
| Questions     | Extra questions organisers ask attendees: yes/no, choice or text.                                     | `EventQuestion`, `QuestionKind`                  |
| Bring list    | Things attendees claim to bring.                                                                      | `EventBringItem`, `EventBringClaim`              |
| Shopping list | Things someone has to buy, optionally linked to the expense that paid for them.                       | `EventShoppingItem`                              |
| Ride          | A car going to or from the event, with its passengers.                                                | `EventRide`, `RideDirection`                     |
| Expense       | Money someone paid for the event, split between attendees.                                            | `EventExpense`, `EventExpenseShare`, `SplitMode` |
| Payment       | Money one attendee paid another to settle up.                                                         | `EventPayment`                                   |
| Settle up     | The fewest transfers that make everyone even.                                                         | `SettleUp`                                       |

## Stats

| Term          | Meaning                                                                                                      | Code name                                  |
| ------------- | ------------------------------------------------------------------------------------------------------------ | ------------------------------------------ |
| Stats         | Server activity: messages, reactions, voice and games.                                                       | `App\Stats`, `StatsQuery`                  |
| Voice session | A stretch of time a member spent in a voice channel.                                                         | `StatVoiceSession`                         |
| Game session  | A stretch of time a member's Discord status showed a game.                                                   | `StatActivitySession`                      |
| Time together | Time two members spent in the same voice channel.                                                            | `StatDailyVoicePair`                       |
| Rollup        | The daily totals the stats pages read, rebuilt by a job.                                                     | `RollUpStats`, `StatDaily*`                |
| Opt-out       | A member's choice to keep their activity out of stats (privacy settings, `/privacy`). It never hides quotes. | `PurgeMemberStats`, `HandlePrivacyCommand` |

## Words to avoid

| Avoid                           | Say instead                             | Why                                                                             |
| ------------------------------- | --------------------------------------- | ------------------------------------------------------------------------------- |
| "guild" in UI copy and docs     | "server"                                | The UI says Servers. Code keeps `Guild`, the Discord API's name.                |
| "user" for someone quoted       | "person"                                | People outside the servers can be quoted; a user is only someone who logged in. |
| "delete" a quote                | "remove"                                | A removed quote can be restored.                                                |
| "organizer", "host" on its own  | "organiser" (creator or co-host)        | The UI uses British spelling, and "host" alone leaves out the creator.          |
| "matchup" in UI copy            | "this or that"                          | The page and sidebar say This or that. Code keeps `QuoteMatchup`.               |
| "revision" in UI copy           | "edit history"                          | Code keeps `QuoteRevision`.                                                     |
| "event" on its own in code talk | "event" (planning) or "broadcast event" | `App\Events` holds Reverb broadcast payloads, not planning events.              |
