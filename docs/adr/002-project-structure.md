# ADR-002: Project Structure (Domain Folders)

- **Status:** Accepted
- **Date:** 2026-10-20
- **Deciders:** Mahmoud Nagah
- **Related issues:** #4

## Context

RYADA has roughly a dozen domains (Club, Player, Membership, Training, Tournament, Bracket, Scoring, Certificate, Plan, Notification, ...). Business logic is written as Action classes with DTOs. In a default Laravel layout, `app/Models`, `app/Actions`, and `app/DTOs` would grow into flat folders of 50+ files, and everything related to one domain would be spread across several folders.

## Options considered

- **A. Domain folders inside `app/`:** business logic grouped per domain; HTTP layer stays in `app/Http`.
- **B. Self-contained modules** (`app/Modules/*` or `nwidart/laravel-modules`): each module owns its routes, controllers, and providers. More isolation, but more boilerplate than a solo-maintained project needs.
- **C. Default Laravel layout:** simplest, but flat folders do not scale to this many domains.

## Decision

We use **Option A**.
app/
Domain/
<Domain>/
Actions/
DTOs/
Enums/
Models/
Policies/
Shared/ # cross-domain pieces, e.g. BelongsToClub trait
Http/
Controllers/Api/V1/
Requests/
Resources/

Rules:

1. Business logic lives in `app/Domain/<Domain>` as Actions that take DTOs. Multi-step operations use `DB::transaction`.
2. Controllers are thin: validate with a Form Request, build a DTO, call an Action, return an API Resource.
3. Code in `app/Domain` must not depend on `app/Http` (no Requests, Resources, or `request()` inside Actions).
4. Policies and factories are registered explicitly with `#[UsePolicy]` and `#[UseFactory]` on the model, because auto-discovery does not look inside domain folders.
5. A domain folder is created when its first issue starts, not in advance.

## Consequences

### Positive

- Everything about one domain is in one place.
- Domain boundaries are visible in the folder tree.
- No extra packages; PSR-4 for `App\` already covers `app/Domain`.

### Negative

- Policies and factories need explicit attributes.
- Contributors must follow rule 3 by convention (we can enforce it later with an architecture test).
