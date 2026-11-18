# Contract: drawing adapter (DT, only after a go on WP-E0)

Directory `resources/js/edit-engine/`. The only place that imports `@open-pencil/*` (H14, FR-110, ESLint `no-restricted-imports`). Loaded as its own bundle with a dynamic import from the Tekenen page. ADR 0016, addendum chapter 7.

## Interface

```ts
interface DrawingEngine {
  mount(element: HTMLElement, scene: DrawnCanvas, options: MountOptions): Promise<void>
  getLayout(): LayoutPayload
  onChange(listener: (change: FinishedAction) => void): () => void
  select(ids: string[]): void
  getSelection(): string[]
  apply(command: DrawingCommand): void
  undo(): void
  redo(): void
  zoomToFit(): void
  destroy(): void
}
```

- `scene` is the layout engine's output (`layout-engine.md`), so the drawing starts from the same boxes and text lines as Bekijken (H11).
- `getLayout()` returns the payload of `layout-payload.md` for every Area other than Direction and every Element; it never returns a node the input did not hold (H12).
- `onChange` fires once per finished action (pointer released, key handled, command applied), within 100 ms (P-7); if the library has no change event, the adapter compares the scene after each finished action (addendum 7.4).
- `DrawingCommand`: align, distribute, equal width, equal height, bring forward, send backward, set style, paste style, apply style to siblings, reset to automatic (element, domain); and for the Shift move `previewMove(elementId, targetId)` (the panel route: places the block in the target at its landing spot and returns the move intent), `commitMove(elementId, targetId, layout)` (reparents the node in the scene after the server accepted, and records the move on the undo stack), `revertMove(elementId)` (puts the block back after cancel or refusal). Alignment and distribution are computed in the adapter when the library lacks them, and applied through the library's commands so undo keeps working (addendum 7.5).
- `FinishedAction` is either a Layout change or a move intent `{kind: 'move', elementId, targetId, layout}` from a Shift drag released over a valid target, with the block already pushed inside the target's width and, when wider, narrowed to it with its Manual children pushed left and narrowed (assumption QS-8, ADR 0036). The page asks "Verplaatsen naar <doel>?"; on "Annuleren", Escape or a refusal it calls `apply({kind: 'revertMove', elementId})` and the block returns to its place; a confirmed move enters the undo stack only after the server accepted it (`commitMove`). `undo()` and `redo()` on a move entry change nothing in the scene; they emit the reverse or repeated move intent, which the page confirms (assumption QS-9) and sends like any move. While the dialog is open or a move is being sent, the page disables input to the drawing.
- `exportImage` from addendum chapter 7 is left out: PNG comes from the HTML view (ADR 0015).

## Restrictions the adapter enforces

No node is created, deleted or renamed by any tool, shortcut, Delete or Backspace, and the library never reparents a node by itself: a drop over another frame without Shift is pushed back, and with Shift over a valid target (the innermost parent under the pointer that may hold the block: a Domain for a Cluster, a Domain or Cluster for an Activity, a Group for an Item, same kind of Area) it becomes a move intent for the page (WP-E0 P5, P10). While Shift-dragging, the adapter shows "Naar <doel>" or "Kan hier niet: <reden>" as text next to the target, including "(wordt smaller)" when the block will be narrowed (assumption QS-8), and suppresses the library's own Shift behaviour such as an axis lock; the Direction band and logo are a locked background; children sticking out of their parent are pushed back before `getLayout()` returns and the pushed-back ids are reported (FR-103). Tools for shapes, text, pen, components, images, AI and collaboration are not shown and their shortcuts do nothing (US35.10).

## Tests at this seam

Round trip (layout in, scene, layout out, no difference); restriction tests (P5); Shift move tests: intent emitted only with Shift over a valid target, innermost-target choice, push inside the target's width, narrowing of a wider block and its Manual children, no reparent by the library, target labels, return on cancel and on refusal (P10); undo and redo per action (P8); picture comparison against Bekijken for three reference Canvases (FR-116); upgrade script that runs these tests against a newer library version.
