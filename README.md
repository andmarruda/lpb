# Laravel Page Builder (LPB)

A page builder backend for Laravel with SQL (Eloquent) and MongoDB storage.
Laravel validates, stores and serves pages; the frontend framework (React,
Vue, Svelte or Angular packages, in a separate repository) mounts and renders
them.

- **One canonical page tree**: a nested, versioned JSON document
  (`format_version` 1) identical for both drivers.
- **Widgets defined in code** with a fluent schema builder producing JSON
  Schema 2020-12; configs validated on every write and upgraded step by step
  between widget versions.
- **Atomic tree saves** with optimistic concurrency (`tree_version`).
- **Draft and published content**: revisions only on publish, retention of
  the last 3, rollback and restore to draft.
- **Revision-keyed cache**: published payloads never need invalidation;
  publishing only moves a pointer.
- **Signed webhooks** for external frontends.
- **HTTP APIs** for delivery and the editor, plus Blade shell views for the
  Inertia and view flows.

Design decisions are recorded in [docs/adr](docs/adr) (ADR 07–13).

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12 or 13
- For MongoDB: the `mongodb` PHP extension and `mongodb/laravel-mongodb` ^5.0
- For the Inertia flow: `inertiajs/inertia-laravel`

## Installation

```bash
composer require andmarruda/lpb
php artisan vendor:publish --tag=lpb-config
```

### SQL driver (MySQL, PostgreSQL, SQLite)

```bash
php artisan vendor:publish --tag=lpb-migrations-sql
php artisan migrate
```

```env
LPB_DATABASE_DRIVER=sql
LPB_SQL_CONNECTION=mysql
```

### MongoDB driver

```bash
composer require mongodb/laravel-mongodb:^5.0
```

```env
LPB_DATABASE_DRIVER=mongodb
LPB_MONGODB_CONNECTION=mongodb
```

Create the collections with their validators and indexes (safe to re-run
after every upgrade):

```bash
php artisan lpb:install-mongo
```

See [docs/MONGODB_SCHEMA.md](docs/MONGODB_SCHEMA.md) for the document structure.

### Permissions

Editor routes require an authenticated user and two Gates, which deny until
your application defines them:

```php
Gate::define('lpb.edit', fn (User $user) => $user->isEditor());
Gate::define('lpb.publish', fn (User $user) => $user->isPublisher());
```

## Widgets

A widget is a PHP definition plus one render component per frontend:

```php
use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

class Hero extends WidgetDefinition
{
    public string $name = 'hero';
    public int $version = 2;

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('title')->required()->maxLength(120)->label('Title')
            ->media('image')
            ->array('buttons', fn (Schema $b) => $b->string('label')->required()->link('link'));
    }

    // Migrates a config stored at version $from to $from + 1
    public function upgrade(int $from, array $config): array
    {
        if ($from === 1 && isset($config['button'])) {
            $config['buttons'] = [$config['button']];
            unset($config['button']);
        }
        return $config;
    }
}
```

Register definitions in `config/laravel-page-builder.php`
(`widgets.definitions`) or with `Lpb::widgets()->register(Hero::class)`.

```bash
php artisan lpb:make-widget Hero --frameworks=react,vue   # definition + component stubs
php artisan lpb:types                                     # TypeScript types of every config
php artisan lpb:widgets:schema --output=widgets.json      # definitions and JSON Schemas
php artisan lpb:upgrade-content                           # rewrite stored configs to current versions
```

The built-in library (48 widgets: heading, text, image, button, tabs,
accordion, carousels, price table, countdown, form, nav menu…) is registered
by default. Its `html` widget outputs raw HTML and is only registered with
`LPB_RAW_HTML_WIDGET=true`; enable it only when every editor is trusted.

Field types: `string`, `integer`, `number`, `boolean`, `enum`, `array`,
`object`, `media` (`{"$media": id}`, resolved in payloads), `link` and
`richText` (HTML; renderers sanitise it). Modifiers: `required`, `default`,
`min`, `max`, `maxLength`, `pattern`, `label`, `help`.

## Media

Image, video and audio widgets reference media records (`{"$media": id}`);
delivery payloads resolve them to `{id, url, mime_type, meta}`. Files can be
uploaded and replaced from the editor API (`POST /editor/media/upload`,
`POST /editor/media/{id}/replace`) or in PHP:

```php
use Andmarruda\Lpb\Media\MediaStorage;

$media = app(MediaStorage::class)->upload($request->file('photo'), ['meta' => ['alt' => 'Team']]);
app(MediaStorage::class)->replace($media['id'], $request->file('new_photo')); // same id, new file
```

Where files go is `LPB_MEDIA_DISK`:

| Value | Storage |
|---|---|
| `public` (default), `local` or any disk of `config/filesystems.php` | Laravel filesystem disk |
| `s3` | Amazon S3 (`composer require league/flysystem-aws-s3-v3`) |
| an S3-compatible disk such as Cloudflare R2 | see below |
| `database` | The database itself (`lpb_media_files`), served from `GET /lpb/api/media/{id}/{file}` |

```php
// config/filesystems.php — Cloudflare R2
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'), // https://<account>.r2.cloudflarestorage.com
    'url' => env('R2_PUBLIC_URL'),     // public bucket or custom domain
    'use_path_style_endpoint' => true,
],
```

`laravel-page-builder.media.disks` lists extra disks editors may choose per
upload. Types are detected from the file contents (`media.mimes`; SVG is not
allowed by default because it can carry scripts) and limited to
`media.max_size` KB. Uploads get generated names, so replacing a file
changes its URL; replacing or deleting media bumps the design version so
cached payloads pick up the new URL. Database-stored files are served with
`nosniff` and a sandboxing Content-Security-Policy; keep them below ~12 MB on
MongoDB (16 MB document limit).

## The page tree

```json
{
  "format_version": 1,
  "page": { "id": "…", "slug": "home", "title": "Home", "seo": {}, "metatags": [] },
  "template": { "id": "…", "slug": "landing" },
  "revision_id": "01JB…",
  "design_version": 7,
  "blocks": [
    {
      "id": "01JB…", "type": "flex",
      "styles": [{ "breakpoint": null, "state": null, "props": { "gap": "24px" } }],
      "widgets": [{ "id": "01JB…", "widget": "hero", "schema_version": 2, "config": { "title": "Welcome" }, "styles": [] }],
      "children": []
    }
  ]
}
```

- Blocks are `flex` or `grid`; each holds `widgets` (rendered first) and
  `children`. Node ids are ULIDs; new nodes bring client-generated ids.
- Styles are variants per breakpoint (`desktop`, `tablet`, `mobile` by
  default) and state (`hover`, `focus`, `active`) with kebab-case CSS
  properties; values may be `{"$var": "token"}`.
- Trees are limited to `limits.max_depth` levels (10) and `limits.max_nodes`
  nodes (1000).

```php
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Contracts\PublishingServiceInterface;

$page = app(PageRepositoryInterface::class)->create(['title' => 'Home', 'slug' => 'home']);

$trees = app(PageTreeRepositoryInterface::class);
$tree = $trees->getWorkingTree($page['id']);
$tree['blocks'] = [/* … */];
$trees->saveTree($page['id'], $tree, $tree['tree_version']); // TreeVersionConflictException if someone saved first

$publishing = app(PublishingServiceInterface::class);
$revision = $publishing->publish($page['id'], by: (string) auth()->id());
$publishing->rollback($page['id'], $olderRevisionId);
```

Status changes only through the publishing service (`publish`, `unpublish`,
`archive`, `unarchive`, `rollback`, `restoreToDraft`); pages are created as
drafts.

## Delivery

```php
use Andmarruda\Lpb\Delivery\PageDeliveryService;

$payload = app(PageDeliveryService::class)->published('home'); // null when not published
```

Payloads are cached under `lpb:content:{revisionId}:{designVersion}`
(immutable) behind a short pointer `lpb:pointer:{site}:{locale}:{slug}`
that publishing events rewrite. `php artisan lpb:design:bump` (also run
automatically when a template changes) gives every page a new content key.

### Three ways to boot a page

| Flow | Use | How |
|---|---|---|
| Inertia | React / Vue / Svelte with Inertia | `return Lpb::inertia('home');` — the template's `view_path` is the root view, the payload is the `page` prop |
| View | Any framework without Inertia, including Angular | `return Lpb::view('home');` or `LPB_PAGES_ROUTE=/{slug}` — the shell embeds the payload and the framework mounts it |
| External | Next, Nuxt, SvelteKit, Angular SSR | Delivery API plus webhooks |

A shell view renders the `<head>` server-side and leaves the page to the
frontend:

```blade
<head>
    <x-lpb::head :page="$lpb" />
    @vite('resources/js/app.js')
</head>
<body>
    <div id="lpb-app"></div>
    @lpbPayload
</body>
```

The default shell is `lpb::shell` (`php artisan vendor:publish --tag=lpb-views`).

### HTTP API

Under `/lpb/api` (see [docs/openapi.yaml](docs/openapi.yaml)):

- Delivery: `GET pages/{slug}` (with ETag), `GET menus/{location}`,
  `GET preview/{token}` (signed, expiring URL).
- Editor: widgets, pages (tree, publish, unpublish, archive, unarchive,
  revisions, rollback, restore, preview token), templates, menus and media.
  Validation errors carry JSON pointers into the tree
  (`/blocks/0/widgets/1/config/title`); conflicts return 409 with
  `current_version`.

### Webhooks

```env
LPB_WEBHOOK_ENDPOINTS=https://front.example/api/revalidate
LPB_WEBHOOK_SECRET=change-me
```

On publish, unpublish, archive, rollback and design bumps a queued job POSTs
`{event, site, locale, slug, revision_id, design_version}` with
`X-Lpb-Signature: t={time},v1={hex HMAC-SHA256 of "{time}.{body}"}`.
Receivers can verify it with
`Andmarruda\Lpb\Webhooks\WebhookSignature::verify($body, $header, $secret)`.

## Testing

Unit tests need no database or Laravel application:

```bash
composer test
```

Integration suites install the package into a fresh Laravel application in
Docker and run the unit and feature tests against a real database:

```bash
composer test:sqlite   # SQLite
composer test:mysql    # MySQL 8.4
composer test:mongo    # MongoDB 7 with lpb:install-mongo applied
composer test:all      # all three
```

Most feature tests are shared scenarios in `tests/Feature/Concerns` that run
identically on every driver.

MySQL `JSON` columns and MongoDB documents may return object keys in a
different order than they were written; values and list order are preserved.

## License

MIT

## Credits

Created by Anderson Arruda
