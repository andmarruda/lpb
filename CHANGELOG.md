# Changelog

## 0.1.0-beta.1 (unreleased)

First open beta of the Laravel package. Migrations may still change until
`v0.1.0` (ADR 13).

### Page tree
- One canonical, nested page tree (`format_version` 1) for SQL and MongoDB;
  blocks (`flex`, `grid`) hold `widgets` and `children`; ULID node ids.
- MongoDB stores the tree nested in the page document.
- Depth and node limits (`limits.max_depth`, `limits.max_nodes`).
- Style variants per breakpoint and state with `$var` design tokens.

### Widgets
- Widgets defined in code with a fluent schema builder (JSON Schema 2020-12),
  validated with opis/json-schema on every write; step-by-step `upgrade()`.
- Built-in library of 48 widgets (the raw `html` widget is opt-in).

### Media
- Uploads and replace (same id, new file) to any filesystem disk (local,
  S3, Cloudflare R2, ...) or to the database, served by the package.
- `lpb:make-widget`, `lpb:types`, `lpb:widgets:schema`, `lpb:upgrade-content`.

### Editing and publishing
- Atomic `saveTree` with optimistic concurrency (`tree_version`).
- Draft working tree and published revisions; retention of the last 3;
  publish, unpublish, archive, unarchive, rollback and restore to draft.

### Delivery
- Published payloads cached by revision and design version; publishing only
  moves a pointer. `lpb:design:bump`, automatic on template changes.
- Signed, queued webhooks (`X-Lpb-Signature`).
- Delivery and editor JSON APIs (docs/openapi.yaml) with JSON pointer
  validation errors; Blade shell (`<x-lpb::head>`, `@lpbPayload`),
  `Lpb::view()` and `Lpb::inertia()`.

### Compatibility
- PHP 8.2+, Laravel 10–13; SQLite, MySQL, PostgreSQL (through Eloquent) and
  MongoDB 7.
