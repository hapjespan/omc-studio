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
- `DrawingCommand`: align, distribute, equal width, equal height, bring forward, send backward, set style, paste style, apply style to siblings, reset to automatic (element, domain). Alignment and distribution are computed in the adapter when the library lacks them, and applied through the library's commands so undo keeps working (addendum 7.5).
- `exportImage` from addendum chapter 7 is left out: PNG comes from the HTML view (ADR 0015).

## Restrictions the adapter enforces

No node is created, deleted, renamed or reparented by any tool, shortcut, Delete or Backspace; the Direction band and logo are a locked background; children sticking out of their parent are pushed back before `getLayout()` returns and the pushed-back ids are reported (FR-103). Tools for shapes, text, pen, components, images, AI and collaboration are not shown and their shortcuts do nothing (US35.10).

## Tests at this seam

Round trip (layout in, scene, layout out, no difference); restriction tests (P5); undo and redo per action (P8); picture comparison against Bekijken for three reference Canvases (FR-116); upgrade script that runs these tests against a newer library version.
