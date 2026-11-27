---
paths:
    - 'app/*/Data/**'
---

# Data

## DTOs are readonly classes

DTOs are plain `final readonly` classes with promoted public properties in the domain's `Data` folder; don't use spatie/laravel-data. DTOs built from Discord JSON expose `static fromPayload(array $payload): self`.
