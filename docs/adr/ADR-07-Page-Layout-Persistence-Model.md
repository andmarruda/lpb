# ADR 07: Page Layout Persistence Model

**Status**: Proposed

**Date**: 2026-10-26

**Author**: Anderson Arruda

**Amends**: ADR 03 (widget ordering), ADR 05 (position persistence)

## Context

ADR 05 says widget positions are stored "in database as coordinates" and
ADR 03 orders widgets by `position_x, position_y`. Phase 1 implemented
something else: blocks that nest through `parent_id` and are ordered by an
integer `order`, with widget instances as leaves.

Elementor, the reference product, went the same way. Its page data is a
recursive tree of elements whose position is their index in the parent's
`elements` array; layout comes from CSS props on containers (flexbox and,
since 4.2, grid with column/row span and order per child). Elementor stores
no coordinates, and replaced its section/column model with containers to cut
DOM depth.

Coordinates do not survive responsive layouts: the same page renders at many
widths, so an absolute (x, y) is only meaningful for one of them.

## Decision

A page layout is an **ordered tree**.

- **Blocks** are containers. A block has a parent block or none (root), an
  `order` among its siblings, a `type` (e.g. `flex`, `grid`) and layout props
  (direction, gap, alignment, grid tracks, child span) stored in its styles
  (see ADR 10).
- **Widget instances** are leaves inside a block, ordered by `order`.
- Position is **parent + order**. Nothing stores x/y coordinates.
- Every driver returns the tree in the same shape (ADR 12 defines the
  canonical format).

The widget ordering library (ADR 05) keeps its spatial engine for the drag
interaction (hit testing, snapping, insertion cursor), but its output is a
tree operation: *move node N to parent P at index I*.

## Consequences

**Positive**:
- Responsive by construction: the browser lays out the tree with flexbox/grid.
- Matches what Phase 1 already ships on both drivers.
- Tree diffs, revisions and component reuse work on a stable structure.

**Negative**:
- Free-form absolute positioning (design-tool style canvases) is not
  supported; it would need an explicit `absolute` block type later.
- ADR 05's persistence section and ADR 03's ordering rule are superseded.

**Mitigation**:
- Grid blocks with span and order per child cover most "place it here" needs.
- Amend ADR 05 and ADR 03 to point here.
