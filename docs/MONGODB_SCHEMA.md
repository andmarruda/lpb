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
| `lpb_pages` | Pages with the embedded block tree, metatags and SEO | `slug` | `status`, `template_id`, `block_ids` |
| `lpb_templates` | Layout templates | `slug` | `status`, `is_default` |
| `lpb_menus` | Menus with embedded items | `slug` | `location, _id`, `items._id` |
| `lpb_media` | Records of files already stored on a disk | `disk, path` | `mime_type`, `collection, filename`, `filename` |
| `lpb_revisions` | Published page snapshots (pruned to the newest `revisions.keep`) | | `revisionable_type, revisionable_id, created_at desc` |
| `lpb_global_settings` | Key/value settings | `key` | |

The exact validators and index definitions live in
`Andmarruda\Lpb\Database\MongoSchema`.

## Validated fields

Every listed field is checked for its type when present; required fields must
always be present.

| Collection | Required | Constraints |
|---|---|---|
| `lpb_pages` | `title`, `slug`, `status` | `slug` matches `^[a-z0-9]+(?:-[a-z0-9]+)*$`; `status` is `draft`, `published` or `archived`; `seo` is a document or null; `tree_version` is a non-negative integer; `block_ids` is a list of strings; `metatags` items need `name` and `content`; root `blocks` need an id, a `type` of `flex` or `grid`, a non-negative `order`, `widgets` and `children`, and their widgets need an id, `widget`, `schema_version` and `order` |
| `lpb_templates` | `name`, `slug`, `view_path`, `is_default`, `status` | `status` is `active` or `inactive`; `is_default` is a boolean |
| `lpb_menus` | `name`, `slug`, `location` | `items` need an id, `label`, `url`, `target` (`_self`, `_blank`, `_parent`, `_top`) and a non-negative `order` |
| `lpb_media` | `disk`, `path`, `filename`, `mime_type`, `size` | `size` is a non-negative integer; `meta` is a document, an empty array or null |
| `lpb_revisions` | `revisionable_type`, `revisionable_id`, `payload`, `status` | `status` is a publish state; `tree_version` is a non-negative integer |
| `lpb_global_settings` | `key` | |

## Embedding and referencing

| Relationship | Storage | Why |
|---|---|---|
| Page → blocks → widgets and child blocks | Embedded in the page document, nested like the canonical tree | A page is read and saved as a single document |
| Page → metatags, SEO | Embedded in the page document | Always read together with the page |
| Page → template | Referenced by `template_id`, with a copy of the template's slug in `template_slug` | Templates are shared by many pages; the copy avoids a lookup when resolving views. The template repository keeps it in sync when a slug changes and clears it when a template is deleted |
| Menu → items | Embedded in the menu document | A menu renders with a single read |
| Media | Own collection, referenced by id | Shared across pages and queried on its own |
| Revisions | Own collection, referenced by `revisionable_type` + `revisionable_id`; the page's `published_revision_id` points to the served one | Snapshots must not grow the page document |

### Trees inside a document

**Blocks** are stored nested, in the shape of the canonical page tree:
each block embeds its `widgets` and its `children` blocks, and every block
and widget keeps an `order` among its siblings. `block_ids` lists every
block id of the tree and is indexed, so a block can be found by id without
knowing its path.

```json
{
  "title": "Home", "slug": "home", "status": "published",
  "tree_version": 12, "published_revision_id": "6ae6…",
  "block_ids": ["01JB…A", "01JB…B"],
  "blocks": [
    { "_id": "01JB…A", "type": "flex", "order": 0, "styles": [],
      "widgets": [{ "_id": "01JB…W", "widget": "hero", "schema_version": 2, "config": {}, "styles": [], "order": 0 }],
      "children": [{ "_id": "01JB…B", "type": "grid", "order": 0, "styles": [], "widgets": [], "children": [] }] }
  ]
}
```

`$jsonSchema` cannot describe a recursive structure, so the validator checks
the root blocks and their widgets only; the repositories validate every
level (ids, types, styles, widget configs and the
`laravel-page-builder.limits` depth and node limits) before writing. The
configured depth stays far below MongoDB's 100-level nesting limit.

**Menu items** are stored as a flat array. Each item has its own id and
points to its parent through `parent_id` (null for root items); the
repository rejects parents from another menu and parents that would create
a cycle.

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

`saveTree` replaces the whole tree with one update filtered by the page id
and the expected `tree_version`, which it increments: of two concurrent
saves against the same version one wins and the other gets a conflict. No
multi-document transaction is needed.

The node-level block operations and menu item writes rewrite the embedded
array without that check, so concurrent node-level writes to the same page
or menu can overwrite each other; editors should use `saveTree`.

## Connections

Models use `laravel-page-builder.connections.mongodb`
(`LPB_MONGODB_CONNECTION`), falling back to the `mongodb` connection, and the
installer uses the same setting unless `--connection` is given.
