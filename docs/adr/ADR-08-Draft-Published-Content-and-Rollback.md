# ADR 08: Draft and Published Content, Revisions and Rollback

**Status**: Proposed

**Date**: 2026-10-26

**Author**: Anderson Arruda

**Amends**: Phase 1 revision behaviour; Project.md Phase 7 ("revisions table with diffs")

## Context

In Phase 1, a page has one tree. Editing the blocks of a published page
changes the live site immediately, and a revision is recorded on every
status transition, including unpublish.

Authors need to edit a published page without visitors seeing half-finished
work, and to go back to a previous published version quickly. Elementor
(through WordPress revisions) stores full snapshots on save and publish, and
computes differences only when comparing.

## Decision

### Working tree and published snapshot

- The page's blocks and widget instances are the **working tree** (the
  draft). Saving changes the working tree only.
- **Publishing** freezes the working tree, together with SEO, metatags,
  template reference and the widget schema versions, into an immutable
  **revision snapshot**, and points the page's `published_revision_id` at it.
- **Visitors are always served the published snapshot**, never the working
  tree. Previews render the working tree.

### Revisions

- A revision is created **only on publish**. Saving drafts, unpublishing and
  archiving do not create revisions.
- Snapshots are complete, not diffs. A compare screen computes the diff
  between two snapshots when it is opened.
- **Retention**: the last **3** published revisions are kept
  (`laravel-page-builder.revisions.keep`, default 3). Older ones are pruned
  when a new revision is published. The revision currently pointed to by
  `published_revision_id` is never pruned.
- Revisions are otherwise immutable: never updated, deleted only by pruning.

### Rollback

- **Rollback** moves `published_revision_id` to one of the retained
  revisions. Nothing is copied; the old snapshot is served again.
- **Restore to draft** copies a retained snapshot into the working tree
  (through the atomic tree save of ADR 11), so it can be edited and
  published as a new revision.
- **Unpublish** clears `published_revision_id`; the page is no longer served.

## Consequences

**Positive**:
- Editing never leaks to visitors; publish is an explicit, single step.
- Rollback is instant (a pointer change) and works with immutable cache
  keys (ADR 09).
- Storage stays bounded: at most `keep` snapshots per page.

**Negative**:
- Two copies of a published page exist (working tree and snapshot).
- History older than `keep` versions is lost by design.
- Phase 1 behaviour changes: revisions are no longer recorded on every
  transition, and pages need a `published_revision_id`.

**Mitigation**:
- `keep` is configurable for sites that need longer history.
- The working tree and the published snapshot are compared to show an
  "unpublished changes" indicator in the editor.
