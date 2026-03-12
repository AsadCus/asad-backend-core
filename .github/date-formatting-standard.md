# Date Formatting Standard

This project uses a backend-provided formatted date strategy to prevent inconsistent frontend formatting.

## Core Rule

- For modules that use date fields or date pickers, expose formatted date attributes from backend using:
    - `translatedFormat('d F Y')`
- Do not rely on frontend-only formatting for canonical display values.

## Preferred Implementation

Define formatted helpers on model accessors and read them in services.

Example:

```php
public function getDateOfBirthFormattedAttribute(): ?string
{
    return $this->date_of_birth
        ? Carbon::parse($this->date_of_birth)->translatedFormat('d F Y')
        : null;
}
```

## Service Response Contract

- When a date is sent to frontend, provide formatted key via accessor naming convention:
    - `*_formatted` (example: `date_of_birth_formatted`)
- Keep raw date keys for form input compatibility when needed by date picker fields.
- Display components should prefer `*_formatted` values.

## Forbidden Pattern

- Do not repeatedly hand-format response dates in service/controller with mixed formats such as `format('Y-m-d')` for display contexts.

## Naming and Consistency

- Accessor naming must be predictable and aligned with base field names.
- Use the same format across modules so AI-generated changes do not introduce date formatting drift.
