# Status of screen 4 (Bekijken without overlay), T381

T383 merges this into the README and deletes this file.

1. Zoom toolbar and page header: opgelost for the toolbar (IconButtons of 28 px, "Passend maken" as small button, zoom value between them). The disabled zoom state uses the contract pattern (`bg-disabled`, hairline border, muted text). The mode switch above the title is rendered by shell/AppShell.vue for every canvas page: open, shared with T380, the integrator resolves it.
2. Direction band: opgelost. Dashed "Logo" box (160 by 92, about the board proportion) on a 40% white dashed border, because the dashed token fails contrast on ink. Labels uppercase, 11 px, 0.05 em, `#C9CED6`. Goals numbered, the title bold, no colon, wrapped lines indented under the title. The hover text and the detail panel number the goals too, and "Strategische doelen" is one key (`view.direction.goals`) used by the controllers and the client.
3. Area headers: opgelost. At the top left, uppercase Space Grotesk 13, letter spacing 0.04 em, accounted for in the wrapping width. Cause of the old misplacement: browsers centre the content of a button vertically.
4. Domain and Cluster titles: opgelost, same cause as point 3.
5. List Area Groups overlapping Items: opgelost. Same centring cause plus card padding applied to list blocks that the list flow does not reserve.
6. Colours of the cards: unchanged, they matched the contract.
7. Spacing between Areas: unchanged, opgelost. The gaps are the engine's fixed 12 px (AREA_GAP); the spread seen in the old screenshot was a measurement artefact.
8. "Rand A3": opgelost. 1 px solid ink line, 11 px muted label, hatch of ink at 6% in 45 degree lines below it on overflow (colour kept in canvas.css as an rgb literal, not derived from the token).
9. Read-only detail panel: depends on T380. It is the foundation's docked white panel; it is complete when T380 rebuilds the panel shell.
