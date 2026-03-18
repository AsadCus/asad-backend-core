# Agent Rules — Travel Management System

> Binding for ALL agentic tools: Amazon Q, GitHub Copilot, Cursor, Windsurf, Claude, ChatGPT, and any other AI agent.
> Goal: zero hallucination, full codebase alignment, scalable quality across all developer skill levels.

---

## 1. Understand Before You Act

- **Read the relevant existing file(s) before writing or modifying anything.**
- Check sibling files for naming, structure, and pattern conventions before creating anything new.
- If a service, component, hook, rule, or helper already exists — use it. Do not duplicate.
- When scope is unclear, ask the user before proceeding.

---

## 2. Stack & Version Lock

Never suggest or introduce alternatives to this fixed stack.

| Layer | Technology | Version |
|---|---|---|
| Backend | Laravel | v12 |
| PHP | PHP | 8.2 |
| Auth | Laravel Fortify | v1 |
| Frontend | React + Inertia.js | React v19, Inertia v2 |
| Routing | Laravel Wayfinder | v0 |
| Styling | Tailwind CSS | v4 |
| Validation (FE) | Zod + react-hook-form | latest |
| Testing | PHPUnit | v11 |
| Formatter (PHP) | Laravel Pint | v1 |
| Formatter (JS) | Prettier v3 + ESLint v9 | latest |
| PDF | barryvdh/laravel-dompdf | v3 |
| Permissions | spatie/laravel-permission | v6 |
| Activity Log | spatie/laravel-activitylog | v4 |

---

## 3. Backend Architecture Rules (Laravel)

### 3.1 Layer Responsibilities

| Layer | Responsibility |
|---|---|
| Controller | Thin. Receive request → call Service → return response. No business logic. |
| Service (`app/Services/`) | All business logic. One service per domain entity. |
| Rule (`app/Rules/`) | Complex validation that doesn't belong in Form Requests. |
| Model | Relationships, casts, accessors only. No business logic. |
| Enum (`app/Enums/`) | TitleCase keys. e.g. `QuotationStatus::Approved`. |
| Helper (`app/Helpers/`) | Stateless utility functions only. |

### 3.2 Controllers
- Always use Form Request classes for validation — never validate inline.
- Return `Inertia::render()` for page responses.
- Use named routes and `route()` for URL generation.
- Never use `env()` directly — always use `config()`.

### 3.3 Models
- Explicit return types on all relationship methods.
- Use `casts()` method, not `$casts` property — follow existing model conventions.
- Eager load relationships to prevent N+1 queries.
- Prefer `Model::query()` over `DB::`.
- Date display accessors must use `translatedFormat('d F Y')` and follow `*_formatted` naming.

### 3.4 Migrations
- Never modify existing migration files. Always create a new one.
- When modifying a column, include ALL previously defined attributes — do not silently drop them.

### 3.5 PHP Code Style
- Always use curly braces for control structures, even single-line.
- Always declare explicit return types on methods and functions.
- Use PHP 8 constructor property promotion.
- Use PHPDoc blocks for complex types. No inline comments unless logic is genuinely complex.
- Run `vendor/bin/pint --dirty` after every PHP change.

---

## 4. Frontend Architecture Rules (React + Inertia)

### 4.1 Directory Structure
```
resources/js/
├── actions/          ← Wayfinder-generated. Never hand-edit.
├── components/       ← Shared UI components
│   └── ui/           ← shadcn/Radix primitives
├── hooks/            ← Custom React hooks (use* prefix)
├── layouts/          ← Page layout wrappers
├── lib/              ← Stateless utility functions
├── pages/            ← Inertia page components (one folder per module)
│   └── [module]/
│       ├── index.tsx       ← List page
│       ├── create.tsx      ← Create page
│       ├── edit.tsx        ← Edit page
│       ├── form.tsx        ← Shared form logic
│       ├── schema.ts       ← Zod schema
│       ├── validation.ts   ← Validation helpers
│       └── components/     ← Module-scoped components only
├── routes/           ← Wayfinder-generated route bindings. Never hand-edit.
└── types/            ← Global TypeScript types
```

### 4.2 Components
- Always check `resources/js/components/` before creating a new component.
- Reuse: `data-table.tsx`, `form-field.tsx`, `combobox.tsx`, `date-picker.tsx`, `proper-input.tsx`, `proper-input-select.tsx`, `confirm-popup.tsx`, `multi-select.tsx`, etc.
- Module-scoped components → `pages/[module]/components/`.
- Shared components → `components/`. Requires user approval before adding.

### 4.3 Protected Components — DO NOT MODIFY without AsadCus approval
- `resources/js/components/proper-input.tsx`
- `resources/js/components/proper-input-select.tsx`
- `resources/js/components/form-field.tsx`
- `resources/js/components/date-picker.tsx`
- `resources/js/components/date-range-filter.tsx`

If a change to any of these is needed, stop and ask:
1. Do you have permission from AsadCus to modify this file?
2. Do you know exactly what change is needed?
3. Has AsadCus reviewed the pending change?

If any answer is No — do not edit.

### 4.4 Forms
- Use Inertia `<Form>` component or `useForm` hook — follow the existing page convention.
- Zod schema in `schema.ts`, validation helpers in `validation.ts`.
- Never duplicate schema definitions across files.

### 4.5 Routing & Navigation
- Use Wayfinder imports from `@/actions/` for controller-bound routes.
- Use `@/routes/` for named routes.
- Use `<Link>` or `router.visit()` — never raw `<a>` tags for internal navigation.
- Run `php artisan wayfinder:generate` after any route change.

### 4.6 Styling (Tailwind v4)
- Use `@import "tailwindcss"` — not `@tailwind` directives.
- Theme extensions go in `@theme {}` in CSS — no `tailwind.config.js`.
- Use `gap-*` for spacing between siblings in flex/grid — not margins.
- Support `dark:` variants if existing pages in the module do.
- Do not use deprecated v3 utilities (`bg-opacity-*`, `flex-shrink-*`, etc.).

---

## 5. Naming Conventions

| Context | Convention | Example |
|---|---|---|
| PHP classes | PascalCase | `ManifestService` |
| PHP methods | camelCase | `getFormattedDate()` |
| PHP variables | camelCase | `$financialYear` |
| Enum keys | TitleCase | `QuotationStatus::Approved` |
| DB columns | snake_case | `date_of_birth` |
| Formatted date accessors | `*_formatted` | `date_of_birth_formatted` |
| React components | PascalCase | `ManifestMemberCard` |
| React hooks | `use` prefix | `useQuotationSectionStatus` |
| TS types/interfaces | PascalCase | `TravelerRow` |
| TS/TSX files | kebab-case | `manifest-member-information-card.tsx` |
| Zod schemas | camelCase | `manifestSchema` |

---

## 6. Date Formatting Standard

Full spec: `.github/date-formatting-standard.md`

- Backend display dates → `translatedFormat('d F Y')` via Carbon accessor.
- Accessor naming → `*_formatted` (e.g. `date_of_birth_formatted`).
- Keep raw date keys for form input / date picker fields.
- Frontend display components must consume `*_formatted` — never re-format raw dates on the frontend.
- Never use `format('Y-m-d')` for display contexts.

---

## 7. Manifest Module — Special Rules

Full spec: `.github/manifest-form-behavior.md`

- `roomLists` shape is `Record<string, TravelerRow[]>` — never use fixed keys like `roomListMakkah`.
- `travelers` is the canonical list. `airlineList` and room lists are projections of it — not independent records.
- Passport field is `passport_number` only. No aliases (`ppt_no`, `passport_no`).
- These shared fields must sync across ALL tabs when edited in any tab:
  `passport_number`, `nationality`, `gender`, `date_of_birth`, `age`, `date_of_issue`, `date_of_expiry`, `issue_place`, `role`.
- New fields must be defined once in `schema.ts` / `types.ts` and wired from the canonical traveler source.
- Extend existing manifest helpers before introducing new transformation paths.

---

## 8. Testing Rules

- Every change must have a corresponding test — new or updated.
- Feature tests by default. Unit tests for isolated logic only.
- Use model factories and their existing states — do not manually construct models in tests.
- Tests must cover: happy path, failure path, and edge cases.
- Run minimal tests: `php artisan test --filter=TestName` or `php artisan test tests/Feature/FileName.php`.
- Never remove test files or test cases without explicit user approval.

---

## 9. Actions That Require Approval

| Action | Approval From |
|---|---|
| Modify any protected component | AsadCus |
| Add a new base-level directory | User |
| Add or change any composer/npm dependency | User |
| Remove any test file or test case | User |
| Modify an existing migration file | User |
| Add a new global shared component | User |

---

## 10. Anti-Hallucination Checklist

Run through this before writing any code:

- [ ] I have read the relevant existing file(s) in the codebase.
- [ ] I checked for an existing service / component / hook that already does this.
- [ ] I am not duplicating any function, component, or logic.
- [ ] Naming follows the conventions in Section 5.
- [ ] I am using the correct stack versions from Section 2.
- [ ] I am not calling `env()` directly outside of config files.
- [ ] I am not using deprecated Tailwind v3 utilities.
- [ ] I am not modifying a protected component without approval.
- [ ] My change has a test (new or updated).
- [ ] I ran `vendor/bin/pint --dirty` for any PHP changes.
- [ ] I ran `php artisan wayfinder:generate` if routes changed.

---

## 11. Skill-Level Guidance

### Junior
- Read at least 2–3 sibling files before writing anything new.
- Do not create new services, rules, or components without confirming none exists.
- Never add a dependency without asking the user first.
- Follow the directory structure exactly — do not improvise folder names.
- When unsure, ask. Do not guess.

### Mid
- Understand the Service layer pattern before touching business logic.
- Validate that your Zod schema matches the backend Form Request rules field-for-field.
- Ensure eager loading is in place before returning relational data from a service.
- Check that formatted date accessors are used in service responses, not raw dates.

### Senior
- Enforce the canonical data shape in Manifest — no shortcuts or one-off mappings.
- Ensure cross-tab sync contracts are maintained when touching manifest logic.
- Confirm new accessors follow the `*_formatted` date standard.
- Confirm Wayfinder routes are regenerated after route changes.
- Review that new Form Requests follow the same array/string rule style as siblings.

### Expert
- Audit for N+1 queries on every new data-fetching path.
- Ensure PHPUnit tests cover happy path, failure path, and edge cases for every change.
- Validate that new migrations do not silently drop column attributes.
- Confirm activity log coverage (`spatie/laravel-activitylog`) for new model mutations.
- Ensure no business logic leaks into controllers or models.

---

## 12. Reference Map

| Purpose | File |
|---|---|
| Full stack & boost rules | `.github/copilot-instructions.md` |
| Date formatting standard | `.github/date-formatting-standard.md` |
| Manifest form behavior | `.github/manifest-form-behavior.md` |
| Dev notes | `app/Docs-DevBadar/gg.txt` |
| Global TS types | `resources/js/types/index.d.ts` |
| App entry (FE) | `resources/js/app.tsx` |
| Route definitions | `routes/web.php`, `routes/auth.php`, `routes/settings.php` |
| Bootstrap config | `bootstrap/app.php`, `bootstrap/providers.php` |
