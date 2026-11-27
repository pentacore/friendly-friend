---
name: cleaning-up-after-a-merge
description: 'Use as soon as a pull request you worked on is merged (or closed for good): stop your leftover processes, drop your test databases, and remove your worktree and branch.'
---

# Cleaning up after a merge

When your pull request is merged, leave nothing behind: the Sail container, the database server and the repository are shared with every other session. Clean up only what is yours. Never stop another session's process, drop its database or remove its worktree or branch.

## 1. Make sure the work is really in

- `gh pr view <number> --json state -q .state` says `MERGED` (or the owner told you the work is abandoned).
- `git fetch origin` and `git log --oneline origin/main..<branch>` prints nothing. If it prints commits, they never landed: stop and say so instead of deleting them.
- `git status --short` in the worktree is empty. A stray rewrite of a generated file (the Typefinder output) can be discarded; anything else, stop and ask.

## 2. Stop your processes

All commands run in the `laravel.test` container (`docker exec -u sail friendly-friend-laravel.test-1 …`).

- **An SSR server you started** from the worktree: `php artisan inertia:stop-ssr`, run in the worktree's folder. Leave the main checkout's SSR server alone.
- **Orphaned Playwright servers**: a browser run that was killed leaves its `playwright run-server` behind, handed to PID 1. Stop them with `pkill -KILL -P 1 -f 'playwright run-server'`. This only matches orphans: a live run's server is always a child of its PHP process, so another session's run is never hit. `tests/Pest.php` does the same at the start of every test run (`Tests\Support\OrphanedPlaywrightServers`).
- **Zombie Chrome processes** (`chrome-headless <defunct>` in `ps`): they have already exited and can't be killed. PID 1 reaps them, because `laravel.test` runs with `init: true` (`compose.yaml`). Check with `ps -o args= -p 1`: it should be `/sbin/docker-init …`. If it isn't and `ps -eo stat | grep -c '^Z'` keeps growing, the container predates `init: true`: ask the owner before recreating it (`vendor/bin/sail up -d` in the main checkout), because that interrupts every session using it.

## 3. Drop your test databases

The worktree's database and the copies parallel runs made, nothing else:

```bash
docker exec friendly-friend-pgsql-1 psql -U sail -d postgres -Atc "select datname from pg_database where datname = 'testing_<name>' or datname like 'testing\_<name>\_test\_%'"
```

Drop each one it lists with `DROP DATABASE "<datname>"`, then run the query again: it should list nothing.

## 4. Remove the worktree and the branch

From the main checkout (`/home/penta/friendly-friend`), never from inside the worktree:

```bash
git worktree remove .claude/worktrees/<name>
git push origin --delete <branch>
git branch -d <branch>
git fetch --prune origin
```

`git branch -d` refuses a branch that isn't merged: that's the point, don't switch to `-D` without checking step 1 again. GitHub may have deleted the remote branch on merge already; then the `push --delete` fails harmlessly.

## 5. Say what you did

Report what you stopped, dropped and removed, and anything you left because it wasn't clearly yours.
