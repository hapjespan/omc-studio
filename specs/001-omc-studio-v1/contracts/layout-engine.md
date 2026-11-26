# Contract: layout engine (D1, extended in DT)

Directory `resources/js/layout/`. Pure TypeScript: no DOM, no network, no Vue. ADR 0029.

## Entry point

`computeCanvasLayout(input: CanvasLayoutInput, measurer: TextMeasurer): DrawnCanvas`

## Port

```ts
interface TextMeasurer {
  width(text: string, font: FontSpec): number
}
```

`FontSpec` is family (Inter or Space Grotesk), size, weight and italic. Browser implementation: canvas 2D `measureText`, called only after `document.fonts.load` resolved for the fonts in use. Test implementation: a fixed width per character.

## Input

- `artboard`: 1680 by 1188; Direction band height 180 (ADR 0020).
- `areas`: the eight Areas with key, kind and position.
- `directionLabels`: the labels of the mission, vision and goals columns (`mission`, `vision`, `goals`).
- `elements`: active Elements with id, Area, parent, kind, name, position, Modality letter and, for the Unknown allocation Domain, its flag.
- `statements`: mission, vision, goals in order.
- `layouts` (DT; empty in D1): per Area or Element id `{x, y, width, height, z, style}`.
- `defaults`: the Default colours and type sizes of `design-contract.md`.

## Output

- `blocks`: per Area and Element `{id, kind, x, y, width, drawnHeight, z, textLines: string[], style}` in absolute Artboard pixels, `style` resolved (Element style over Default colours, ink or white text by contrast per FR-046).
- `direction`: the four columns with their lines and a `truncated` flag per column (SD-40). The goals column numbers its lines (`"<n>. <title> <body>"`, no colon) and returns `marks`, one entry per wrapped line: `number` (length of the `"<n>. "` prefix on the first line of a goal, else 0) and `bold` (characters of the line, after the prefix, that belong to the title). Goal text is wrapped at weight 600 and 18 px narrower than the column, the hanging indent under the number; Area headers are measured with a letter spacing of 0.04 em.
- `warnings`: `emptyCluster`, `domainWithoutActivities`, `beyondArtboard` (Domains and Groups partly or wholly below 1188), `directionTextCut`, and from DT `overlap` pairs (FR-040).
- `beyondArtboardCount`: for the notice of US16.8.

## Rules it implements

FR-106a (names wrap inside the width, a word longer than the width breaks; blocks grow in height, never in width; parents grow; growth never moves a Manual Element), FR-107 (Automatic layout of kickoff 6.5, deterministic), FR-108 placement of Elements without a Layout row (R-6), automatic siblings under the lowest Manual child (source point 54), and the miniature mode of R-21 (`depth: 'domains'`).

## Consumers

Bekijken renderer (D1), Invoeren warnings (D1), overview miniatures (D1), Tekenen adapter scene build (DT), PNG and print (D3, through the Bekijken view).

## Tests at this seam

Vitest with the fake measurer: arrangement of the eight Areas, flow and wrap of Domain cards, Activities before Clusters, growth chains, words longer than a width, Warnings, determinism (same input twice gives deep-equal output), 500 Elements under 50 ms. In DT: Manual layout precedence, placement under the lowest child, "Alles automatisch opmaken" equals a new Canvas (SC-028).
