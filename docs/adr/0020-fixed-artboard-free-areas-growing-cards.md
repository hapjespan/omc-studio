# 0020: Lay out on a fixed A3 Artboard with free Areas and cards that grow with their names

Date: 2026-11-18
Status: accepted (product owner, decisions 34, 35, 36 and 48, 2026-11-18); what happens when content does not fit the Artboard is open (`[GATE-C51]`)

## Decision

Every Canvas is laid out on a fixed Artboard of 1680 by 1188 pixels (landscape A3), in Automatic layout from delivery 1 and in Manual layout from Tekenen on. The Direction band (the Area Direction) with the logo is fixed across the top in four columns, at a fixed height. The other seven Areas can be moved and resized in Tekenen within the Artboard below the Direction band; they cannot be added, removed or renamed (H2). Every child stays inside its parent horizontally. Names always wrap, and an Element grows in height to hold its name and its children, also when its size was set by hand: a stored height is a minimum. Growth pushes its parents taller and never moves other Elements; Elements that end up overlapping are listed as a Warning. An Element or Area cannot be made narrower than the right edge of its rightmost child. The server checks stored places and sizes; content grown beyond the Artboard is open (`[GATE-C51]`).

## Motivation

The product owner wants a picture that prints as one A3 page, free arrangement of the Areas, and names that are never cut off. Storing the height as a minimum lets names grow without the server having to measure text, and keeps the check "child inside parent" decidable on stored values. Not moving other Elements keeps a hand-made arrangement intact; a Warning makes the overlap visible instead. Rejected: locked Areas (addendum K13, A9) and cutting names off (addendum 6.2). A growing Artboard was the advice on point 36 and was not chosen; point 51 may still revisit how content beyond the Artboard is handled.
