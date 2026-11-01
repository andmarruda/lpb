# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project
uses [Semantic Versioning](https://semver.org/).

## [0.1.0-beta.1] "Kessel Run" — 2026-11-01

First open beta of the Laravel package. The HTTP API and the canonical page
format are stable enough to build on; database migrations may still change
until `v0.1.0` (ADR 13). Full description:
[docs/releases/0.1.0-beta.1-kessel-run.md](docs/releases/0.1.0-beta.1-kessel-run.md).

### Added

#### Page tree
- One canonical, nested page tree (`format_version` 1), identical on SQL and
  MongoDB: `flex` and `grid` blocks holding `widgets` and `children`, ULID
  node ids (client-generated ids are kept), per-node style variants by
  breakpoint and state with `$var` design tokens.
- MongoDB stores the tree nested in the page document, with an indexed
  `block_ids` list.
- Depth and node limits (`limits.max_depth`, `limits.max_nodes`).
- `tree_version` incremented by every change to snapshot content.

#### Widgets
- Widgets defined in code: `WidgetDefinition` with a fluent `Schema` builder
  producing JSON Schema 2020-12 (`string`, `integer`, `number`, `boolean`,
  `enum`, `array`, `object`, `strings`, `media`, `link`, `url`, `richText`).
- Configs validated with opis/json-schema on every write, errors reported as
  JSON pointers; step-by-step `upgrade()` between widget versions, applied on
  read.
- Built-in library of 48 widgets with labels, categories and example
  configs; the raw `html` widget is opt-in (`LPB_RAW_HTML_WIDGET`).
- Commands: `lpb:make-widget`, `lpb:types`, `lpb:widgets:schema`,
  `lpb:upgrade-content`.

#### Editing and publishing
- Atomic `saveTree` with optimistic concurrency (409 with the current
  version on conflict, all-or-nothing validation).
- `PublishingService`: publish, unpublish, archive, unarchive, rollback,
  restore to draft, unpublished-changes flag and retained revisions (last 3
  by default, never the served one).
- Events: `PagePublished`, `PageUnpublished`, `PageArchived`,
  `PageRolledBack`, `PageSlugChanged`, `PageDeleted`, `TemplateChanged`,
  `MediaChanged`, `DesignVersionBumped`.

#### Delivery
- `PageDeliveryService`: published payloads cached under
  `lpb:content:{revisionId}:{designVersion}` behind a short pointer
  `lpb:pointer:{site}:{locale}:{slug}`; previews from the working tree.
- Design version in global settings; `lpb:design:bump`, also automatic on
  template changes and media replacement or deletion.
- Signed (HMAC-SHA256, `X-Lpb-Signature`), queued and retried webhooks for
  publish, unpublish, archive, rollback, page deletion, slug changes and
  design bumps.

#### Media
- `MediaStorage`: upload, replace (same id, new file and URL), delete and
  URLs for any filesystem disk (local, public, S3, Cloudflare R2 or other
  S3-compatible storage) or the `database` disk, served from
  `GET /lpb/api/media/{id}/{file}`.

#### HTTP and views
- Delivery API: pages (with ETags), menus by location, signed previews,
  database-stored media.
- Editor API: widgets, pages (tree, publishing, revisions, rollback, restore,
  preview token), templates, menus, media upload and replace; protected by
  configurable middleware and the `lpb.edit` / `lpb.publish` Gates.
- Blade shell: `<x-lpb::head>`, `@lpbPayload`, the publishable `lpb::shell`;
  `Lpb::view()`, `Lpb::inertia()` and an optional pages route.
- `docs/openapi.yaml`.

#### Compatibility and tooling
- PHP 8.2+, Laravel 10–13; SQLite, MySQL 8.4 and MongoDB 7 test suites on
  PHP 8.2 and 8.3; GitHub Actions workflow.

### Changed
- Pages are created as drafts; status changes only through the publishing
  service (`transition()` and status changes through `update()` were removed).
- Revisions are created only on publish and store the working tree snapshot
  with its `tree_version`.
- `CacheKey` reworked into `CacheKey::content()` and `CacheKey::pointer()`.

### Removed
- `extra_css` and `extra_js` on pages.
- Unused `table_prefix` and `performance` configuration.

### Security
- Gates deny editing and publishing until the application defines them.
- Link, menu and widget URL fields accept only http(s), mailto, tel and
  relative URLs; style values cannot escape their CSS declaration;
  `$dynamic` references are rejected until specified.
- Uploads: types detected from the file contents, size limit, generated
  names, SVG off by default; database-stored files served with `nosniff`
  and a sandboxing Content-Security-Policy.
- Media records can only point at allowed disks and relative paths without
  traversal; editors change only media details, not the stored file.
- Widgets that are no longer registered are left out of delivered payloads.

[0.1.0-beta.1]: https://github.com/andmarruda/lpb/releases/tag/v0.1.0-beta.1
