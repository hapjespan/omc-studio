# Foundation completion (T384) and reflow spec (T385): evidence

Branch `feat/d1-20f-foundation-complete`, cut from `release` at `da69400`.

## T384: delivery 1 before and after

Method: the five delivery 1 screens (sign-in, overview, invoeren, bekijken, gebruikers) at 1440 by 900, full page, chromium, reduced motion, signed in as the administrator of the neutral seed `.scratch/compare-seed.php` (organisation "Voorbeeld BV", canvas "Voorbeeldcanvas"). Before: the tree at the start of the work (release). After: the tree with the shared foundation. Each pair was decoded in the browser into a canvas and compared pixel by pixel (any channel difference counts).

| Screen | Size before and after | Differing pixels |
|---|---|---|
| sign-in | 1440 by 900 | 0 |
| overview | 1440 by 900 | 80, box x 1128 to 1143, y 82 to 97 |
| invoeren | 1440 by 1526 | 80, box x 269 to 284, y 193 to 208 |
| bekijken | 1440 by 900 | 0 |
| gebruikers | 1440 by 900 | 0 |

The 80 differing pixels on overview and invoeren are one 16 by 16 box each: the archive `CheckBox`, now with the 4 pixel radius of the contract instead of the browser default. This is the requested `CheckBox` restyle and the only visible difference.

Where else the `CheckBox` restyle shows in delivery 1 (not in the five shots, same component):

- the archive toggle of the list view, so the Organisaties list (`archivable`);
- the row and select-all boxes of a list view with bulk actions (`bulkActions`); the Gebruikers list has neither the toggle nor bulk actions, which is why its pair is identical.

The shell status region keeps its visible text by default: the quiet (visually hidden) mode is an opt-in of a page (`useQuietStatus()`), so no delivery 1 confirmation (copied password, flash statuses) disappears. The text is not part of the five shots because no message is posted in them.

The checked and indeterminate marks were checked on zoomed screenshots of the harness page.

## T384: browser checks

`tests/e2e/components/foundation.spec.ts` measures, on the harness page `FoundationDemo.vue`, the computed size, colour and accessible name of the segmented control (36 outside, 32 for the segment), chip select, panel, file field (36 box, 28 button), link-mode button, danger pill and callout, checkbox (16 in 24, radius 4), icon button sizes, action menu (keyboard), token swatches, header targets, the opt-in wide padding (24 against the default 16) and the quiet status region (at most 1 by 1 pixel, still announced). It also passes axe.

## T385: investigation

Failure: `tests/e2e/shell/reflow.spec.ts`, "at 200% text the shell still does not scroll horizontally" failed after a long run (overflow 10 pixels after opening the Menu) on the accumulated end-to-end database of the second session and passed on a fresh one.

- Cause: not data in itself but the landing page. A fresh database has no canvas cards on the Overzicht; an accumulated one has hundreds. In the second session's branch, which is older than the delivery 1 design work, the Overzicht toolbar did not wrap and the card placeholder and card were a fixed 240 pixels without `max-w-full`. At 200% text on a 320 pixel viewport a card is wider than the column, so the page scrolls by a few pixels.
- On release the markup already carries `flex-wrap` and `max-w-full` (`Overview.vue`, `CanvasCard.vue`). The failure could not be reproduced on release: a copy of the accumulated database (202 canvases, 225 organisations) plus an organisation and canvas with 120 character unbreakable names gave an overflow of 0 with the Menu open, before and after the miniatures were drawn.
- Red proof: with `max-w-full` removed from the card, the miniature, the placeholder and the list item, the new spec fails with an overflow of 19 pixels. Restored, it passes.
- Fix: no layout change on release. The spec no longer depends on the landing page contents: it seeds its own organisation and canvas with long names (3 repeats plus a random suffix, within the 150 character limit of `organisations.name`), waits for the organisation heading and the canvas link, and keeps the tolerance at 0. `createCanvas` takes optional names and escapes quotes and backslashes.
- Runs: passes on a fresh database and, twice in a row, on the copy of the accumulated database.
