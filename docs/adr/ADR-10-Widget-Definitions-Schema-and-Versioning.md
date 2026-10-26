# ADR 10: Widget Definitions, JSON Schema and Schema Versioning

**Status**: Proposed

**Date**: 2026-10-26

**Author**: Anderson Arruda

## Context

Widget instances store a free-form `config` JSON object. Nothing checks it,
and when a widget changes the shape of its config, stored pages break.

Elementor 4 moved from untyped widget settings to typed props, separated
styles (variants per breakpoint and state) from content settings, and gave
every element a schema `version` so stored data can be upgraded. Its typed
value envelope (`{"$$type": ..., "value": ...}` on every value) is powerful
but verbose, and has caused production failures (elementor/elementor#36208).

The requirement here is validation and safe evolution **without making
widgets harder to write**.

## Decision

### Widget definition

A widget is defined once on the server:

```php
final class Hero extends WidgetDefinition
{
    public string $name = 'hero';
    public int $version = 2;

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('title')->required()->maxLength(120)
            ->string('subtitle')
            ->array('buttons', fn (Schema $b) => $b->string('label')->string('url'));
    }

    public function upgrade(int $from, array $config): array
    {
        // v1 stored a single "button"; v2 stores a "buttons" list
        if ($from < 2 && isset($config['button'])) {
            $config['buttons'] = [$config['button']];
            unset($config['button']);
        }
        return $config;
    }
}
```

- The fluent builder produces standard **JSON Schema**; authors never write
  JSON Schema by hand unless they want to (a raw schema array is accepted).
- The same schema generates the **TypeScript types** for the frontend
  renderers (`php artisan lpb:types`) and drives the editor's property panel.
- `php artisan lpb:make-widget Hero --frameworks=react,vue` scaffolds the
  definition and the render component for each chosen frontend (ADR 12).
- Config is validated against the schema on every write.

### Config, styles and references

- `config` holds content (text, links, media ids).
- `styles` holds presentation, separate from content: a list of variants,
  each with a `breakpoint` (or none) and a `state` (or none) and a set of
  CSS props. Layout props of blocks (ADR 07) live here too.
- Values are plain JSON. A value may instead be a **reference**, written as a
  small object: `{"$var": "color-primary"}` for a design token,
  `{"$media": 42}` for a media record, `{"$dynamic": "post.title"}` for
  dynamic data. No envelope on ordinary values.

### Schema versioning

- Every widget instance stores `schema_version`.
- On read, an instance older than its definition's `version` is passed
  through `upgrade()` step by step. Stored data is rewritten only when the
  instance is next saved, or in bulk with `php artisan lpb:upgrade-content`.
- Revision snapshots are immutable; they are upgraded on read, never
  rewritten.
- The canonical page tree carries a `format_version` for changes to the tree
  format itself (ADR 12).

## Consequences

**Positive**:
- Invalid config is rejected at write time instead of breaking a render.
- Widget authors write one PHP class and one component per frontend; types
  and editor panel come for free.
- Changing a widget never breaks stored pages or revisions.

**Negative**:
- Every widget needs a schema, even a trivial one.
- `upgrade()` steps accumulate over a widget's life.
- Reference objects (`$var`, `$media`, `$dynamic`) must be resolved by every
  renderer.

**Mitigation**:
- An empty schema (`$s`) is valid for widgets without config.
- Old `upgrade()` steps can be dropped after `lpb:upgrade-content` has run on
  all stored data and the oldest retained revision is newer.
- Reference resolution happens server-side when building the delivery
  payload, so renderers receive resolved values.
