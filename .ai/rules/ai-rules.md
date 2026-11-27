---
paths:
    - '.ai/**'
---

# Rules about the rules

## Regenerate after editing .ai

After adding or renaming a rule file, run `vendor/bin/sail artisan boost:index-rules` (never edit `index.md` by hand). After any change under `.ai/`, run `vendor/bin/sail artisan boost:update` so `AGENTS.md`, `CLAUDE.md` and the agents' skill folders are regenerated. Commit the regenerated files with the rule change.

## Narrowest glob, one home per rule

Put a rule in the file whose `paths:` glob is the narrowest that covers it. Only project-wide rules go in `general.md` (`**`). Keep each rule in one file; when it matters to a second area, add a one-line pointer there, not a copy. When an area gains a folder, add it to the area file's globs. `tests/Unit/Architecture/RuleGlobsTest.php` fails on a glob that matches no project file, because an agent never opens a rule whose globs match nothing.

## Tag the owner's decisions

When a rule records a decision the owner made, end it with "(owner's rule, YYYY-MM-DD)". Don't reword, weaken or "fix" such a rule without the owner. (owner's rule, 2026-11-27)

## Rules are constraints, procedures are skills

Keep each rule a short constraint. A multi-step procedure (running a tool a particular way, a setup or release routine) goes in a skill under `.ai/skills/<name>/SKILL.md`, and the rule points at it. Write the skill the second time a workflow is needed, instead of working it out again.
