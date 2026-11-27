---
paths:
    - 'app/Models/**'
---

# Models

## Enum columns

Store enums in a `string()` column and cast to the backed PHP enum on the model; don't use DB `enum()` columns.

## Eager load per query, never $with

Never set a model-level `$with`: it loads the relation on every query, including the ones that don't need it. Eager load at the call site with `->with()`, scoped to the columns a resource or prop uses (`'discordUser:id,discord_id,global_name'`) when that's all it needs.
