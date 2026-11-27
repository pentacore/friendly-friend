---
name: formatting-project
description: 'Use when formatting, cleaning up, linting or finalising code changes in friendly-friend, including requests that mention Rector, Pint, Vite+, Oxfmt, Oxlint or `npm run check:fix`.'
---

# Formatting the project

Run the whole formatting pass from the repository root, through Sail.

## Order

Run every step, in this order:

1. `vendor/bin/sail bin rector --no-progress-bar` (add `--clear-cache` after changing a signature: Rector's cache only re-checks changed files, so a caller that needs a fix can stay unseen until CI)
2. `vendor/bin/sail npm run check:fix` (`vp check --fix`: Oxfmt formats `resources/`, then Oxlint applies its fixes)
3. `vendor/bin/sail bin pint --dirty --format agent`

Rector rewrites PHP before Pint gives it its final style, so Pint always runs last. Don't swap a fix command for its check-only form. If Sail isn't running, start it with `vendor/bin/sail up -d` and start again from step 1.

## Keep every change

Look at `git status --short` and the diff before you start, so the changes that were already there stay recognisable.

Every change Rector, Oxfmt, Oxlint or Pint makes belongs to the formatting pass, even in a file outside the current task.

- Never revert, discard, leave out or unstage a formatter's change because it is out of scope, and never restore a file to shrink the diff.
- If a formatter's output is wrong, fix it forward, then rerun that command and every command after it.
- When committing the pass, include every formatter change unless the user says otherwise.

## Failures

Stop at the first command that fails, without reverting what earlier commands changed. Fix the cause, rerun that command, then carry on down the list. When all three pass, check `git status --short` and the diff, and report every file the pass changed.
