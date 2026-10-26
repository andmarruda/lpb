# Proposed amendments to ADR 01–06

Changes to the existing wiki ADRs that follow from Phase 1 and ADR 07–13.

## ADR 01: Database Abstraction Layer

- Replace the code samples with the current design: one contract per entity
  (`PageRepositoryInterface`, `TemplateRepositoryInterface`,
  `BlockRepositoryInterface`, `MenuRepositoryInterface`,
  `MediaRepositoryInterface`, `RevisionRepositoryInterface`), bound to the SQL
  or MongoDB implementation by the service provider from
  `laravel-page-builder.database_driver` (`sql` or `mongodb`).
- Remove `EloquentRepository`, `MongoRepository`, `resolveModelClass()` and
  `withWidgets()`; they no longer exist.
- Migration guide: MongoDB uses `php artisan lpb:install-mongo`, not published
  migrations.
- Configuration keys are `connections.sql` and `connections.mongodb`, and they
  are now honoured by models and migrations.
- Remove the test counts and the benchmark table, which were not measured;
  link to the test commands in the README instead.
- Add: both drivers return identical shapes; the canonical page tree is
  defined in ADR 12.

## ADR 02: Caching Strategy Part 1

- Replace the key `lpb:page:{slug}:rendered` and the invalidate-on-update
  strategy with ADR 09 (immutable content keys per revision plus pointers).
- "Rendered pages" becomes "page tree payloads": Laravel does not render page
  HTML (ADR 12), so the application cache holds JSON only.

## ADR 03: Page Rendering Pipeline

- Mark as **Superseded by ADR 12**. Blade is not the source of truth and
  Laravel does not render page HTML; rendering happens in the Vue, React or
  Svelte package.
- What survives moves into ADR 12's payload builder: data comes from the
  **published snapshot** (ADR 08), the ordered block tree (ADR 07) and
  validated widget config (ADR 10).
- The ETag and Last-Modified steps are dropped; they also contradicted
  ADR 04, which rules out HTTP cache headers.

## ADR 04: Caching Strategy Part 2

- Fix the cross-reference "ADR 0001" (application cache) to "ADR 02".
- Purge triggers become pointer changes and `designVersion` bumps (ADR 09).
- Add headless revalidation webhooks (ADR 12).
- Reconcile with Project.md Phase 2, which still asks for "HTTP cache headers
  integration and ETag support".

## ADR 05: Custom Widget Ordering Library

- Replace "store widget positions in database as coordinates" with: the
  library's output is a tree move (parent + index), see ADR 07.

## ADR 06: Multi-Framework Widget Library Integration

- Fix the reference "See ADR 0002 for frontend stack details" to ADR 12.
- Add Svelte to the adapter list (ADR 12 supports SvelteKit).
- The adapters become part of the framework packages of ADR 12
  (`@lpb/vue`, `@lpb/react`, `@lpb/svelte`), and the framework-agnostic
  engine part of `@lpb/core`.

## Wiki pages

- "Database MongoDB – Structure" describes a `pages` collection with `theme`,
  `widgets` and `widget_index`. Replace it with docs/MONGODB_SCHEMA.md.
- "Database SQL – Structure" still shows `lpb_page_widgets` and widget
  settings. Regenerate the diagram from the current migrations.
- Project.md "Key technical decisions": the driver variable is
  `LPB_DATABASE_DRIVER=sql|mongodb`, not `PAGE_BUILDER_DRIVER=sql|mongo`;
  revisions are snapshots, not diffs (ADR 08); cache keys follow ADR 09.
