# ADR 12: Frontend Delivery Modes and Canonical Page Tree

**Status**: Proposed

**Date**: 2026-10-26

**Author**: Anderson Arruda

**Fills**: the "frontend stack" ADR planned in Project.md Phase 0 and referenced by ADR 06

## Context

LPB must deliver pages to these kinds of frontends:

| Mode | Server | Client |
|---|---|---|
| React + Inertia | Laravel | React |
| React headless | Laravel API | Any React app (e.g. Next.js) |
| Vue + Inertia | Laravel | Vue |
| Vue headless | Laravel API | Any Vue app (e.g. Nuxt) |
| Svelte + Inertia / SvelteKit | Laravel / Laravel API | Svelte |
| Angular (no Inertia adapter) | Laravel view / Laravel API | Angular (e.g. Angular SSR) |

Supporting this many frontends is only sustainable if most of the work is
shared and each frontend adds a small, well-defined piece.

Laravel is the source of truth for content and validation, but page content
is always rendered by the frontend framework. Blade is used only for the HTML
**shell**: the template's view (`view_path`) outputs the `<head>` (SEO,
metatags), the assets and the mount point, the same role Inertia's root view
plays.

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
      "widgets": [ { "id": "01J...", "widget": "hero", "schema_version": 2, "config": {}, "styles": [] } ],
      "children": [ { "id": "01J...", "type": "grid", "styles": [], "widgets": [], "children": [] } ]
    }
  ]
}
```

- The tree is nested and built by Laravel (PHP) from either driver;
  frontends walk it as received. A block has two ordered lists, `widgets`
  and `children`, and the tree is bounded in depth and size (ADR 07).

- The payload is built server-side from the published snapshot (ADR 08),
  with references resolved (ADR 10), and cached by revision (ADR 09).
- Inertia modes pass it as page props; headless modes fetch it from the
  delivery API (`GET /lpb/api/pages/{slug}`).

### One package per framework, with or without Inertia

Each framework gets one package (`@andmarruda/lpb-react`, `@andmarruda/lpb-vue`, `@andmarruda/lpb-svelte`,
`@andmarruda/lpb-angular`) that contains both halves:

1. **Renderer**: a recursive `<LpbPage>` component that walks the tree,
   renders blocks with their styles and looks widgets up in a registry by
   name, plus the registry where the app registers its widget components.
2. **Editor**: the editing UI (page tree, property panel, drag and drop,
   publish and rollback) written in that framework. Its canvas uses the
   package's own renderer, so the editor shows exactly what will be
   published.

### Three ways to boot a page

| Flow | Who serves the HTML | Where the tree comes from |
|---|---|---|
| **Inertia** | Laravel, through Inertia, with the template's `view_path` as root view | Inertia page props |
| **View** | Laravel renders the template's `view_path`, which embeds the payload and the mount point | Embedded JSON, then the API |
| **External** | The frontend's own server (e.g. SvelteKit) | Delivery API |

The same framework package supports every flow its framework can use; it
only needs to be told where the tree comes from. In every flow the page is
**mounted by the framework**. Angular has no official Inertia adapter, so it
uses the view and external flows.

### Shared, framework-agnostic core

Everything that is not UI lives once in `@andmarruda/lpb-core` (plain TypeScript) and is
used by every framework package:

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

- The `<head>` (title, SEO, metatags) is always server-rendered: by the
  shell view in the Inertia and view flows, by the frontend's server in the
  external flow.
- Server-side rendering of page content is optional and belongs to the
  framework (Inertia SSR, Next, Nuxt, SvelteKit, Angular SSR). Laravel never
  runs a Node rendering step; in the view flow, content is mounted in the
  browser.
- Publish, rollback and `designVersion` changes emit events, and are sent
  as signed webhooks to headless frontends so they can revalidate their own
  caches (ADR 09).

### Conformance

A shared set of fixture trees with expected, normalised HTML output is run
against every renderer in CI, and a shared set of editing scenarios (insert,
move, save conflict, publish, rollback) is run against every editor. A
framework is supported only while its package passes both suites.

### Rollout

React is delivered end to end first (renderer, editor, Inertia, view and
external flows), and the conformance suites are written against it. Vue,
Svelte and Angular follow, in that order, against the same suites.

## Consequences

**Positive**:
- Each framework feels native: renderer and editor are idiomatic components,
  with no iframe protocol between them.
- WYSIWYG in every mode, because the editor canvas uses the real renderer.
- Laravel stays the single source of truth for content and validation; Blade
  is limited to the shell, so content rendering lives in one place per
  framework.
- Most logic (state, saving, validation, drag) is written and tested once in
  `@andmarruda/lpb-core`.

**Negative**:
- The editor UI is implemented once per framework (four times).
- Every widget needs one render component per framework the site uses.
- Sites that need server-rendered content must deploy a Node runtime for
  their framework's SSR (Inertia SSR or an external frontend).
- ADR 03's Blade rendering of page content is superseded; Blade keeps only
  the shell.
- In the view flow, page content renders in the browser; only the `<head>`
  is server-rendered by the shell.

**Mitigation**:
- Keep the per-framework editor code to presentation; anything with logic
  belongs in `@andmarruda/lpb-core`.
- Conformance suites keep renderers and editors consistent.
- `lpb:make-widget --frameworks=...` scaffolds only the frameworks a site
  uses (ADR 10).
