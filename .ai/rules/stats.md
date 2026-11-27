---
paths:
    - 'app/Stats/**'
---

# Stats and privacy

- Stats store **counts and sessions only**. Never store message content.
- Opt-out is checked **twice**: in the bot router (cached opted-out set) before queuing, and again in the job. Opting out deletes all of that person's `stat_*` rows (`PurgeMemberStats`). Opting back in only counts new activity.
- Stats writes are idempotent upserts keyed by their time bucket (`guild_id`, `discord_user_id`, bucket).
- Every stat is per server and can be combined across servers.
