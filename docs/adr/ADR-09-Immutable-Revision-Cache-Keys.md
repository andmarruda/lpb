# ADR 09: Immutable Revision-Based Cache Keys

**Status**: Proposed

**Date**: 2026-10-26

**Author**: Anderson Arruda

**Amends**: ADR 02 (key format and invalidation), ADR 03 (cache stages), ADR 04 (purge triggers)

## Context

Three cache key formats are documented: `lpb:page:{slug}:rendered`
(ADR 02), `pb:page:{tenant}:{locale}:{route}:{rev}` (Project.md) and
`lpb:page:{driver}:{id}` (the Phase 1 `CacheKey` value object). Key-by-slug
caches must be invalidated on every relevant write, which is where
Elementor's best-known problems come from: stale pages and CSS until a
"regenerate CSS" step runs.

ADR 08 makes published content immutable: a published page is a revision
snapshot that never changes.

## Decision

Two kinds of cache entries:

1. **Content entries are immutable and keyed by revision**:
   `lpb:content:{revisionId}:{designVersion}`
   - The entry is the canonical page tree payload (ADR 12) that every
     frontend mode receives. Laravel never renders page HTML, so it never
     caches HTML; server-side rendering and its cache belong to the frontend
     (Inertia SSR, Next, Nuxt, SvelteKit), which revalidates on the events
     below.
   - These entries never need invalidation; they can have a long TTL.
2. **Pointers are short and mutable**:
   `lpb:pointer:{site}:{locale}:{slug}` → `revisionId`
   - Written on publish, rollback and unpublish (ADR 08); that is the only
     invalidation needed for page content.

`designVersion` is a site-wide counter bumped when something outside the
snapshot changes how every page looks: design tokens, global classes,
templates or a deployment that changes widget definitions. Bumping it makes
every content key new without deleting anything.

`site` and `locale` are part of the pointer from the start (`default` until
multi-site and localisation exist) so the format does not change later.

CDN purges (ADR 04) are triggered by pointer changes and `designVersion`
bumps only. Headless frontends receive the same events as webhooks to
revalidate their own caches (ADR 12).

## Consequences

**Positive**:
- No stale content after publish: the pointer moves to a key that was never
  served before.
- Rollback and publish cost one write each.
- One key scheme for application cache, CDN and headless revalidation.

**Negative**:
- Old content entries linger until their TTL expires.
- A `designVersion` bump makes every page miss once (cold cache).

**Mitigation**:
- Bounded TTL on content entries.
- Optional cache warm-up job after a `designVersion` bump.
- The `CacheKey` value object is changed to build both key kinds.
