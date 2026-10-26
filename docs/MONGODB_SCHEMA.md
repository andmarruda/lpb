# MongoDB Schema

How Laravel Page Builder stores its data in MongoDB, and what
`php artisan lpb:install-mongo` sets up.

## Installing

```bash
php artisan lpb:install-mongo                      # uses laravel-page-builder.connections.mongodb
php artisan lpb:install-mongo --connection=tenant  # any other MongoDB connection
```

For each collection the command:

- creates it if it is missing, or replaces its validator if it exists;
- attaches a `$jsonSchema` validator with `validationLevel: moderate` and
  `validationAction: error`, so new and valid documents are checked while
  documents that were already invalid can still be updated;
- creates the indexes below. An index that already exists with the same
  definition is left alone.

The command is safe to re-run after upgrading the package. If a unique index
cannot be built because of duplicate data, the command names the index, keeps
going with the rest and exits with a failure code; remove the duplicates and
run it again.

The repositories validate input before writing, so the validators mainly
protect against documents written around them.

## Collections

| Collection | Purpose | Unique indexes | Other indexes |
|---|---|---|---|
| `lpb_pages` | Pages with embedded blocks, widget instances, metatags and SEO | `slug` | `status`, `template_id`, `blocks._id` |
| `lpb_templates` | Layout templates | `slug` | `status`, `is_default` |
| `lpb_menus` | Menus with embedded items | `slug` | `location, _id`, `items._id` |
| `lpb_media` | Records of files already stored on a disk | `disk, path` | `mime_type`, `collection, filename`, `filename` |
| `lpb_revisions` | Append-only page snapshots | | `revisionable_type, revisionable_id, created_at desc` |
| `lpb_global_settings` | Key/value settings | `key` | |

The exact validators and index definitions live in
`Andmarruda\Lpb\Database\MongoSchema`.

## Validated fields

Every listed field is checked for its type when present; required fields must
always be present.

| Collection | Required | Constraints |
|---|---|---|
| `lpb_pages` | `title`, `slug`, `status` | `slug` matches `^[a-z0-9]+(?:-[a-z0-9]+)*$`; `status` is `draft`, `published` or `archived`; `seo` is a document or null; `metatags` items need `name` and `content`; `blocks` items need an id, `type`, a non-negative `order` and `widget_instances`, whose items need an id, `widget` and `order` |
| `lpb_templates` | `name`, `slug`, `view_path`, `is_default`, `status` | `status` is `active` or `inactive`; `is_default` is a boolean |
| `lpb_menus` | `name`, `slug`, `location` | `items` need an id, `label`, `url`, `target` (`_self`, `_blank`, `_parent`, `_top`) and a non-negative `order` |
| `lpb_media` | `disk`, `path`, `filename`, `mime_type`, `size` | `size` is a non-negative integer; `meta` is a document, an empty array or null |
| `lpb_revisions` | `revisionable_type`, `revisionable_id`, `payload`, `status` | `status` is a publish state |
| `lpb_global_settings` | `key` | |

## Embedding and referencing

| Relationship | Storage | Why |
|---|---|---|
| Page → blocks → widget instances | Embedded in the page document | A page renders with a single read |
| Page → metatags, SEO | Embedded in the page document | Always read together with the page |
| Page → template | Referenced by `template_id`, with a copy of the template's slug in `template_slug` | Templates are shared by many pages; the copy avoids a lookup when resolving views. The template repository keeps it in sync when a slug changes and clears it when a template is deleted |
| Menu → items | Embedded in the menu document | A menu renders with a single read |
| Media | Own collection, referenced by id | Shared across pages and queried on its own |
| Revisions | Own collection, referenced by `revisionable_type` + `revisionable_id` | Unbounded history must not grow the page document |

### Trees inside a document

Blocks and menu items are stored as a flat array. Each element has its own id
and points to its parent through `parent_id` (null for root elements). The
document structure therefore puts no limit on nesting depth; the repositories
reject parents from another page or menu and parents that would create a
cycle. Blocks and items are returned as a flat list ordered by `order`, then
insertion, and callers build the tree.

### Embedded ids

Embedded blocks, widget instances and menu items get a ULID as id. ULIDs are
used instead of ObjectId strings because laravel-mongodb converts 24-character
hex strings to ObjectId when querying `*._id`, which would never match an id
stored as a string.

laravel-mongodb stores an embedded `id` as `_id` and returns it as `id`
(`rename_embedded_id_field`, enabled by default). The installer reads that
setting from the connection and validates and indexes `_id` or `id`
accordingly.

### Concurrency

Writes to blocks or menu items replace the whole embedded array of the page
or menu. Two concurrent writes to the same page or menu can therefore
overwrite each other; writes to different documents are independent.
Standalone MongoDB servers have no multi-document transactions, so the page
repository validates a status transition before writing anything.

## Connections

Models use `laravel-page-builder.connections.mongodb`
(`LPB_MONGODB_CONNECTION`), falling back to the `mongodb` connection, and the
installer uses the same setting unless `--connection` is given.
