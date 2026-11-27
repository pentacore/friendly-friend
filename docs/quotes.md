# Quotes

Quotes are captured in Discord (Phase 4), shown and voted on on the website (Phase 5), and belong to the server they came from (`quotes.guild_id`). People and quotes are one shared pool across servers.

## Data model

| Table                      | What                                                                                                                                                                                                                                                                                                                               |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `people`                   | Anyone who can be quoted. `discord_user_id` is null for people outside the servers. `slug` for URLs.                                                                                                                                                                                                                               |
| `person_aliases`           | Other names a person goes by ("Marre"). Unique, case-insensitive.                                                                                                                                                                                                                                                                  |
| `quotes`                   | `status` (`draft` → `published`; `pending_review` for imports; `removed`), `source`, `context`, `said_at` with precision (`exact`/`day`/`approximate`), source message (unique: a message can only be quoted once), `submitted_by_discord_user_id`, `raw_input`, `ai_output` (debugging), `score`/`elo`/`matchup_count` (Phase 5). |
| `quote_lines`              | Ordered lines: `speaker_name`, `text`, `person_id`. In a **draft**, `person_id` may still be null: `speaker_discord_user_id` set means "this Discord user", neither means "a new person". People are only created when the quote is saved.                                                                                         |
| `quote_revisions`          | A full snapshot (lines, context, said_at) on every save and edit, with the editor.                                                                                                                                                                                                                                                 |
| `quote_imports`            | Channel backfills: cursor, progress, status.                                                                                                                                                                                                                                                                                       |
| `guilds.quotes_channel_id` | Where saved quotes are posted. `guilds.channels` caches the text channels for the picker.                                                                                                                                                                                                                                          |

## Who said it: `App\Quotes\Actions\ResolvePerson`

Checked in order:

1. Known Discord user (message author or mention) → their person, or a link to them that becomes a person on save.
2. Alias, case-insensitive.
3. A person's display name, case-insensitive.
4. A member **of the quote's server**, by server nickname, global display name or username.
5. Fuzzy match (Postgres `pg_trgm` similarity ≥ 0.5) on people and aliases. "Fredrick" finds Fredrik.
6. Otherwise a **new person** with that name.

The preview always shows who each line was matched to, so a wrong guess is caught before saving.

## Lifecycle (`App\Quotes\Actions`)

| Action            | Does                                                                                                                                              |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DraftQuote`      | Creates the draft with resolved lines. A source message that's already saved throws `AlreadyQuotedException`; an unsaved draft of it is replaced. |
| `UpdateDraft`     | Applies the edit form to a draft.                                                                                                                 |
| `ConfirmQuote`    | Creates any missing people (reusing exact-name matches), publishes, records the first revision.                                                   |
| `CancelDraft`     | Deletes a draft.                                                                                                                                  |
| `RecordRevision`  | Snapshot, used by every save and edit.                                                                                                            |
| `WriteDraftLines` | Shared by draft and update: replaces lines, resolving each speaker.                                                                               |
