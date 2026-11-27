---
paths:
    - 'database/migrations/**'
---

# Migrations

## jsonb columns

Use `jsonb()` for JSON columns, not `json()`.

## Enum columns

Store enums in a `string()` column and cast to the backed PHP enum on the model; don't use DB `enum()` columns.

## Environment-only tables

Tables for dev-only tools (Telescope) are created only where the tool runs: `shouldRun()` returns `app()->environment('local')`.
