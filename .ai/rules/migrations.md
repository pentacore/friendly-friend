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

## Foreign keys state their delete rule

Declare a foreign key as `foreignId('x_id')->constrained()` (name the table when the column doesn't) and always chain the delete rule: `cascadeOnDelete()` for rows owned by the parent, `nullOnDelete()` for a nullable link, `restrictOnDelete()` for a row that must not vanish (who paid, who recorded a payment). Seven foreign keys in the event tables (`created_by_discord_user_id`, the cost tables' people) predate this rule and state none, so Postgres uses `NO ACTION`, which behaves like restrict.

## A real down()

Write `down()` as the true inverse of `up()`, data migrations included: drop indexes and foreign keys before their columns, and put back earlier column definitions instead of leaving a migration one-way.
