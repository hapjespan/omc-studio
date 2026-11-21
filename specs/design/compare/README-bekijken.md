# Bekijken with the financial overlay (board 2): status of findings B-01 to B-40 after T386

Branch feat/d210-bekijken. Statuses: closed (with the commit), open (with the reason) or deviating (with the reason). The findings themselves are in `findings-d2.md` of this folder; the screenshot is `bekijken-financieel-built-v2.png`.

Open question for the product owner: should the screen show the Artboard at 100% with horizontal scroll, or fit it to the column? The fit (about 62% at 1440) keeps the whole canvas in view but makes every Artboard text about 0.62 times the size of the board; at 100% the Direction band and Areas run under the side panel unless the page scrolls sideways. Until answered, the screen fits (ASSUMPTION T386-1) and the computed values below are measured at zoom 100%.

Format notes: on the card the forecast against the budget is a whole percentage ("101% van budget", contract), the side panel keeps one decimal ("101,1% van budget", decision 40); the Share keeps one decimal everywhere. The list Areas keep a white fill, not the board #F6F7F5: that is a real constraint (design contract line 52 and `defaultColours.test.ts`), deviating.

Foundation gaps, kept local for now: a SegmentedControl (OverlaySwitch copies the ModeSwitch classes), ChipSelect (the contract's filter chips, Kostenposten has the same need), a page-header target in the shell (`#page-title`, `#page-actions` and `HeaderSlot`), a minus and a fit icon for the zoom buttons, and a token for the over-budget amber #9A7811.

| Finding | Status | Commit and note |
|---|---|---|
| B-01 | closed | 9fc33eb, 66ecd92, 8633db3: one 56px bar plus one header row (title, modes, overlay switch, chips, Exporteren); the canvas starts at y 164 at 1440, the legend line sits above it as on the board. |
| B-02 | closed | 9fc33eb, 66ecd92, 8633db3: the A3 Artboard (1680 wide for PNG and print, ADR 0020) opens fitted to the column width next to the side panel (not below 50%) and follows the width until the user zooms; "Passend maken" fits the whole Canvas, width and height (ASSUMPTION T386-1). |
| B-03 | closed | 9fc33eb: the scroller has no fixed height (at most viewport minus 8rem), the page grows with the fitted canvas; in print the scroller is unbounded (66ecd92, 8633db3). |
| B-04 | closed | 9fc33eb: one side panel; the totals panel (334px, white, radius 10) gives way to the detail panel while a card is selected. |
| B-05 | closed | 22c7240: dashed Logo box, uppercase labels, goals fully visible as a numbered list. |
| B-06 | closed | 22c7240: the button element centred the block text vertically, so titles hid and list Items overlapped their group titles; blocks now align to the top and list blocks have no padding. |
| B-07 | deviating | card heights follow the Activity count (decision 39 and ADR 0020: the overlay draws in the card place and size); the Domain minimum height now reserves the amount line under the title (66ecd92, 8633db3), so empty space under the counts remains in cards with many Activities. |
| B-08 | closed | 1c2ea75: one legend line, three swatches, "streep = budget, balk = prognose" and the list Areas note. |
| B-09 | closed | 9fc33eb: bars under each row with the percentage of the canvas total. |
| B-10 | closed | 9fc33eb: the title is in the unscaled header, Space Grotesk 20/28 600; the breadcrumb is the foundation top bar (T379). |
| B-11 | deviating | Space Grotesk 14 with line height 20 against 19.6 (whole-pixel line heights of the layout engine). On screen at 1440 the Artboard renders at about 62%, so the title is about 8.7px: ADR 0020 (fixed A3 Artboard scaled to fit) against the board full-size canvas. Computed values hold at zoom 100%. |
| B-12 | deviating | 1c2ea75: amount Space Grotesk 22/30.8 700 with the Share beside it at zoom 100%; at the 62% fit it is 13.6px on screen (ADR 0020 against the board full-size canvas). |
| B-13 | deviating | 1c2ea75: Inter 11.5/16.1 #454C57 and a whole percentage ("97% van budget") at zoom 100%; about 7px on screen at the 62% fit (ADR 0020). |
| B-14 | deviating | 1c2ea75: "5 activiteiten · 1 cluster" without a zero cluster count, Inter 11.5 secondary at zoom 100%; about 7px on screen at the 62% fit (ADR 0020). |
| B-15 | closed | 9fc33eb: the switch label is unscaled Inter 12.5 400 #454C57. |
| B-16 | closed | 9fc33eb: unscaled 13px 500, ink active segment, same classes as the foundation mode switch (36px outer, 34px inner). |
| B-17 | closed | 9fc33eb: unscaled Space Grotesk 30/42 700 canvas total with the caption "Canvastotaal 2026 · budget". |
| B-18 | closed | 9fc33eb: unscaled Space Grotesk 13/18.2 600. |
| B-19 | deviating | 22c7240: 11px 600 uppercase with 0.06em spacing at zoom 100%; about 7px on screen at the 62% fit (ADR 0020). |
| B-20 | deviating | 22c7240, 1c2ea75, 66ecd92, 8633db3: Area title top left in uppercase Space Grotesk 13, 0.04em spacing and secondary colour (now part of the style and the measured width), amount in strong ink and percentage in secondary at the right; 8px on screen at the 62% fit (ADR 0020). |
| B-21 | closed | T379 foundation: page #F2F3F1 with white panels; see bekijken-financieel-built-v2.png. |
| B-22 | closed | 1c2ea75: every Domain card is tinted by its Share from 3% to 28%, empty cards at 3%. |
| B-23 | closed | 1c2ea75: 1px hairline on every financial card. |
| B-24 | closed | T379 foundation: the active segment is ink. |
| B-25 | closed | 9fc33eb: success Callout with check icon; the amounts stay in cents (decision 52), as the contract says. |
| B-26 | deviating | the part above the budget is #9A7811 (decision 45, 3.1:1 on the track), not #C9A227; only the part beyond the budget stripe is coloured; the track is 120% of the budget as on the board. |
| B-27 | closed | 22c7240, 66ecd92, 8633db3: the Domain badge is 16 by 16 with radius 4 and an 11/700 letter, in the top right of a Domain card that carries a modality (the seed data has none on Domains). |
| B-28 | closed | 9fc33eb: "Nu toewijzen" is ink and underlined. |
| B-29 | deviating | 1c2ea75: padding 12, radius 8, border 1px at zoom 100%; scaled to 62% on screen (ADR 0020). |
| B-30 | closed | 9fc33eb: white, hairline border, radius 10, padding 16, 334px. |
| B-31 | closed | 9fc33eb: 1px hairline rules between the panel sections. |
| B-32 | deviating | 9fc33eb: chips "Soort Alle", "Eigenaar Alle", "Applicatie Alle" and "Versie Actueel" of 36px with a chevron, native selects inside; "Filteren" and "Openen" appear only after a change, because applying on change would change the context without warning (WCAG 3.2.2). |
| B-33 | closed | 9fc33eb: primary "Exporteren" at the far right; the "Met overlay" switch stays beside it because print and PNG export depend on it. |
| B-34 | closed | 1c2ea75: popover white, 1px ink border, radius 6, padding 8px 10px, rows with percentage and amount. |
| B-35 | deviating | 22c7240, 9fc33eb: a selected card has a 2px ink outline and its detail panel replaces the totals panel (one panel); the panel content has no board counterpart, the read-only detail panel is the contract panel. |
| B-36 | deviating | the zoom controls sit at the right of the legend line as small foundation buttons with text; the foundation has no minus or fit icon (foundation gap). |
| B-37 | deviating | 22c7240: dashed "Logo" box in the Direction band, 154 by 90 at zoom 100% (about 96 by 56 on screen at the 62% fit, as on the board). |
| B-38 | closed | 1c2ea75: see B-34. |
| B-39 | closed | 1c2ea75: "Geen kosten toegewezen in 2026" on a hairline card. |
| B-40 | deviating | the contract focus ring of decision 46 (dark and light ring) takes precedence over the board; d2-keyboard and d2-axe stay green. |
