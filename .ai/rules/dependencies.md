---
paths:
    - 'composer.json'
    - 'composer.lock'
    - 'package.json'
    - 'package-lock.json'
    - '.github/dependabot.yml'
---

# Dependencies

## New production packages need the owner's approval

Don't add a package to `require` in `composer.json` or to `dependencies` in `package.json` without asking the owner first. Record each approval below, with its date, in the commit that adds the package. Development packages (`require-dev`, `devDependencies`) and updates to packages already installed need no approval. Every production package installed on 2026-11-27 counts as approved. (owner's rule, 2026-11-27)

Approved since 2026-11-27:

- (none yet)

## Held updates say why

Dependabot opens weekly pull requests for Composer, npm and GitHub Actions, waiting 5 days after a release (security updates don't wait). Minor and patch updates come grouped per ecosystem and dependency type, majors one by one. When an update has to be held, add an `ignore` entry to `.github/dependabot.yml` with a comment naming the upstream issue that lifts it, and list it under "Held dependency updates" in `ROADMAP.md`.
