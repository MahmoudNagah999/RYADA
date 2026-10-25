# ADR-003: Localization (Arabic and English)

- **Status:** Accepted
- **Date:** 2026-10-21
- **Deciders:** Mahmoud Nagah
- **Related issues:** #39

## Context

RYADA serves Egyptian and Arabic-speaking clubs, so the API and the admin panel must work in Arabic and English. The API is consumed by a separate client (mobile/web), so the server must not depend on a browser session to know the language.

## Decision

1. **Locale resolution** (`SetLocale` middleware): `Accept-Language` header > `users.locale` > default `en`. Supported locales live in `config/locales.php`. The response carries a `Content-Language` header. The middleware is global (registered in `bootstrap/app.php`), so it runs before routing and authentication and error responses such as 401, 404 and 405 are translated too.
2. **Static strings:** translation keys in `lang/en` and `lang/ar`, used through `__()`. Arabic framework translations come from `laravel-lang`.
3. **Stored reference data** (belts, categories, plans): `spatie/laravel-translatable` (JSON columns). Player and club names are stored as entered and never translated.
4. **API errors:** a stable English `code` plus a translated `message`.

```json
   {"code": "player.limit_reached", "message": "Your plan allows a maximum of 50 players."}
```

Domain code throws `BusinessRuleException` with the code and parameters. The exception handler translates it using `errors.<code>`. Clients must branch on `code`, never on `message`.

5. **Dates:** the API returns ISO 8601 UTC; formatting is the client's job.
6. **Definition of Done:** every user-facing string is a translation key present in both `en` and `ar`. `tests/Unit/LangFilesTest.php` enforces that both languages have matching files and keys (and matching placeholders for our own `errors` file). It does not detect hardcoded strings in code; that part is a code review rule.

## Consequences

- Clients must send the user's chosen language in `Accept-Language` on every request. Many HTTP clients add the device language automatically, and the header wins over `users.locale`.
- Adding an error code means adding its key to both languages, otherwise CI fails.
- PDF certificates will need an Arabic-capable library (mPDF or Browsershot, not DomPDF), and notifications and mails must be sent in the recipient's locale. Both are handled in their own issues.

