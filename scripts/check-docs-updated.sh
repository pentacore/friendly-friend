#!/usr/bin/env bash
# Warn (never fail) when app code, schema, routes or config change without any
# change to docs/ or .ai/rules/. Rules and docs ship in the same change.
set -euo pipefail

base="${1:-origin/main}"
changed=$(git diff --name-only "$base"...HEAD)

if echo "$changed" | grep -qE '^(app|database|routes|config)/' \
    && ! echo "$changed" | grep -qE '^(docs|\.ai)/'; then
    echo "::warning::Code changed without updates to docs/ or .ai/rules/. Update rules & docs in the same change."
else
    echo "Rules/docs check OK."
fi
