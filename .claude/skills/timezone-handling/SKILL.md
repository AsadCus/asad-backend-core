---
name: timezone-handling
description: 'How to handle dates and datetimes on the backend so timezones are correct end-to-end. Activate when reading/writing/returning any datetime (check-in/out, approvals, timestamps), writing a service that maps a model to a JSON array, or formatting a Carbon for an API response. The rule: store/compute UTC, return ISO-8601 with offset for datetimes and Y-m-d for dates — never pre-format datetimes to local strings.'
license: MIT
---

# Timezone Handling — Backend

The app runs in **UTC** (`config/app.php` → `'timezone' => 'UTC'`). The database stores UTC, and the
frontend converts to each user's **browser timezone** for display. The backend's only job is to keep
UTC and return datetimes in a form the frontend can convert.

## The rules

1. **Store/compute in UTC.** `Carbon::now()`, model `datetime` casts, and DB values are all UTC. Don't
   change `app.timezone`. Don't `->setTimezone(...)` before storing.

2. **Return datetimes as ISO-8601 with offset.** When you build an API array, use
   **`->toIso8601String()`** (e.g. `2026-06-24T08:00:00+00:00`).

   ```php
   // ✅ datetime → ISO-8601 UTC (frontend converts to the user's TZ)
   'check_in_at' => $a->check_in_at?->toIso8601String(),
   'locked_at'   => $e->attendance_locked_at?->toIso8601String(),

   // ❌ never pre-format a datetime — this bakes in UTC wall-clock; the frontend can't recover the instant
   'time_in' => $a->check_in_at?->format('H:i'),            // shows 08:00 (UTC) to a Jakarta user
   'at'      => $a->check_in_at?->format('Y-m-d H:i:s'),    // no offset → JS parses it as LOCAL, double-wrong
   ```

   A raw `Carbon` placed in an array also serializes to ISO via `jsonSerialize()`, so returning the
   attribute unformatted is fine too — but prefer the explicit `->toIso8601String()`.

3. **Dates (no time) stay `Y-m-d`.** A pure date has no timezone; format it plainly and the frontend
   shows it as-is (no conversion).

   ```php
   'effective_from' => $s->effective_from?->format('Y-m-d'),   // ✅ date — TZ-agnostic
   'depart_at'      => $t->depart_at?->format('Y-m-d'),        // ✅ planning date, not a precise instant
   ```

4. **Parsing incoming datetimes.** `Carbon::parse($str)` interprets a bare `Y-m-d H:i` string as **UTC**
   (app tz). If the frontend sends a *user-entered local* wall-clock that must land on a precise instant
   (e.g. a corrected punch time), the frontend converts it to UTC ISO before sending (see the frontend
   `timezone-handling` skill); the backend then parses the offset correctly. Dates need no conversion.

## Checklist when returning a model in a service
- datetime field → `->toIso8601String()` (or raw Carbon), **never** `->format('…H:i…')`.
- **timestamps (`created_at`/`updated_at`) are instants too** → `->toIso8601String()`, never
  `->format('Y-m-d')` (that drops the time and offset — the frontend can't show "when" correctly).
- date field → `->format('Y-m-d')`. If a field is conceptually a date, cast it as `'date'` (not
  `'datetime'`) so the model is honest and serialization is naturally date-only.
- Pre-formatting time for display is the frontend's job, not the backend's.
