# Foundation gaps F-2, F-6, G-8, K-28 (T386): evidence

Branch `feat/d1-20g-foundation-gaps`, cut from `release`.

## Delivery 1 before and after

Method: the five delivery 1 screens (sign-in, overview, invoeren, bekijken, gebruikers) at 1440 by 900, full page, chromium, reduced motion, signed in as the administrator of the neutral seed `.scratch/compare-seed.php`. Before: the tree at the start of the work, built and shot before any change. After: the final tree, rebuilt. The top bar of each signed-in screen was also shot as an element. Each pair was decoded in the browser into a canvas and compared pixel by pixel (any channel difference counts).

| Screen | Size before and after | Differing pixels | Top bar (1440 by 56) differing pixels |
|---|---|---|---|
| sign-in | 1440 by 900 | 0 | no top bar |
| overview | 1440 by 900 | 0 | 0 |
| invoeren | 1440 by 1526 | 0 | 0 |
| bekijken | 1440 by 900 | 0 | 0 |
| gebruikers | 1440 by 900 | 0 | 0 |

No component is used on a delivery 1 screen; the only shared markup touched is `Field` (a `hideLabel` prop, off by default) and `TopBar` (wrapping classes that only act when content is too wide).

## F-6: top bar at 320 CSS pixels

Harness page `TopBarDemo.vue` mirrors the shell header with an organisation name and a canvas name of 70 and 90 unbreakable characters, the sample pill, the fiscal year slot ("Boekjaar 2026", "Concept") and the role menu.

| | Horizontal overflow of the document |
|---|---|
| Before (failing test first) | 20 pixels, with the Menu open as well |
| After | 0 |

The Menu button still precedes the tabs in tab order, the Menu, fiscal year and role menu all lie inside the bar, and axe passes. The fix is `[overflow-wrap:anywhere]` and `max-w-full` on the breadcrumb and `flex-wrap` with `min-w-0` on the right-hand group.

## Browser checks

`tests/e2e/components/foundation-gaps.spec.ts` (harness `GapsDemo.vue`): inline field 84 by 36, hidden label still the accessible name, "%" suffix, description and error, border colours; native select 36 high, border `#848B95`, labelled empty option, string model, error state; report table card border and radius, caption, column and row headers, right-aligned tabular figures, empty state, axe. `tests/e2e/shell/topbar-narrow.spec.ts`: the 320 pixel top bar.
