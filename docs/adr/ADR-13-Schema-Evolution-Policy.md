# ADR 13: Schema Evolution Policy

**Status**: Proposed

**Date**: 2026-10-26

**Author**: Anderson Arruda

## Context

LPB stores data in three layers that change at different speeds: database
tables and collections, the canonical page tree format (ADR 12), and widget
config (ADR 10). During Phase 1 the SQL migrations were edited in place
because no release existed; that cannot continue once people install the
package.

## Decision

- **Database schema**: migrations may be edited in place **until the first
  tagged release (`v0.1.0`)**. From then on, only new, additive migrations
  are allowed; existing migrations are never edited. MongoDB changes are
  applied by re-running `php artisan lpb:install-mongo`, which is
  idempotent.
- **Page tree format**: `format_version` in the canonical tree. Renderers
  declare the versions they support; the server upgrades older trees when
  building the payload.
- **Widget config**: `schema_version` per widget instance with `upgrade()`
  steps in the widget definition, applied on read and persisted on next
  save or by `php artisan lpb:upgrade-content` (ADR 10).
- **Revisions** are never rewritten; they are upgraded on read through the
  same steps.

## Consequences

**Positive**:
- Installed sites can always upgrade without manual data fixes.
- Content changes (widget shapes) do not require database migrations.

**Negative**:
- After `v0.1.0`, schema mistakes are fixed with new migrations, not edits.

**Mitigation**:
- Phase 1.5 changes that alter tables (ULID ids, published revision
  pointer, tree version) land before `v0.1.0`.
