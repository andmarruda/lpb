# ADR 12: Frontend Delivery Modes and Canonical Page Tree

**Status**: Proposed

**Date**: 2026-10-26

**Author**: Anderson Arruda

**Fills**: the "frontend stack" ADR planned in Project.md Phase 0 and referenced by ADR 06

## Context

LPB must deliver pages to five kinds of frontends:

| Mode | Server | Client |
|---|---|---|
| React + Inertia | Laravel | React |
| React headless | Laravel API | Any React app (e.g. Next.js) |
| Vue + Inertia | Laravel | Vue |
| Vue headless | Laravel API | Any Vue app (e.g. Nuxt) |
| SvelteKit (headless) | Laravel API | SvelteKit |

Supporting five frontends is only sustainable if most of the work is shared
and each frontend adds a small, well-defined piece.

Blade is not one of the modes. Laravel is the source of truth for content and
validation, but it does not render page HTML: rendering always happens in the
frontend framework.

## Decision

### One canonical page tree

The contract between Laravel and every frontend is a versioned JSON
document, identical for both storage drivers:

```json
{
  "format_version": 1,
  "page": { "id": "...", "slug": "home", "title": "Home", "seo": {}, "metatags": [] },
  "template": { "slug": "landing" },
  "revision_id": "...",
  "design_version": 7,
  "blocks": [
    {
      "id": "01J...", "type": "flex", "styles": [],
      "children": [ { "id": "01J...", "type": "grid", "styles": [], "children": [] } ],
      "widgets": [ { "id": "01J...", "widget": "hero", "schema_version": 2, "config": {}, "styles": [] } ]
    }
  ]
}
```

- The payload is built server-side from the published snapshot (ADR 08),
  with references resolved (ADR 10), and cached by revision (ADR 09).
- Inertia modes pass it as page props; headless modes fetch it from the
  delivery API (`GET /lpb/api/pages/{slug}`).

### One package per framework, with or without Inertia

Each framework gets one package (`@lpb/vue`, `@lpb/react`, `@lpb/svelte`)
that contains both halves:

1. **Renderer**: a recursive `<LpbPage>` component that walks the tree,
   renders blocks with their styles and looks widgets up in a registry by
   name, plus the registry where the app registers its widget components.
2. **Editor**: the editing UI (page tree, property panel, drag and drop,
   publish and rollback) written in that framework. Its canvas uses the
   package's own renderer, so the editor shows exactly what will be
   published.

The same package works with Inertia (the tree arrives as page props) and
headless (the tree is fetched from the delivery API); the package only needs
to be told where the tree comes from.

### Shared, framework-agnostic core

Everything that is not UI lives once in `@lpb/core` (plain TypeScript) and is
used by all three packages:

- tree model and editing commands (insert, move, update, delete), undo/redo
  and dirty tracking;
- the API client: load, `saveTree` with `tree_version` (ADR 11), publish,
  rollback;
- validation against widget JSON Schemas and generation of property-panel
  field descriptions from them (ADR 10);
- the drag engine from ADR 05, whose output is a tree move (ADR 07);
- reference resolution helpers and breakpoint/state handling for styles.

A framework package is therefore the renderer plus the editor's components,
bound to the core's state.

### SEO and caching

- Public pages must be server-rendered in every mode (Inertia SSR, Next,
  Nuxt, SvelteKit).
- Publish, rollback and `designVersion` changes emit events, and are sent
  as signed webhooks to headless frontends so they can revalidate their own
  caches (ADR 09).

### Conformance

A shared set of fixture trees with expected, normalised HTML output is run
against every renderer in CI, and a shared set of editing scenarios (insert,
move, save conflict, publish, rollback) is run against every editor. A
framework is supported only while its package passes both suites.

### Rollout

One framework is delivered end to end first (renderer, editor, Inertia and
headless), and the conformance suites are written against it. The other
frameworks follow against the same suites.

## Consequences

**Positive**:
- Each framework feels native: renderer and editor are idiomatic components,
  with no iframe protocol between them.
- WYSIWYG in every mode, because the editor canvas uses the real renderer.
- Laravel stays the single source of truth for content and validation, and
  never renders page HTML.
- Most logic (state, saving, validation, drag) is written and tested once in
  `@lpb/core`.

**Negative**:
- The editor UI is implemented three times, once per framework.
- Every widget needs one render component per framework the site uses.
- SSR is a hard requirement for public pages, which adds a Node runtime to
  Inertia SSR and headless deployments.
- ADR 03's Blade pipeline is superseded.

**Mitigation**:
- Keep the per-framework editor code to presentation; anything with logic
  belongs in `@lpb/core`.
- Conformance suites keep renderers and editors consistent.
- `lpb:make-widget --frameworks=...` scaffolds only the frameworks a site
  uses (ADR 10).
