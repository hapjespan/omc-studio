# 0039: Allow data: and blob: image sources in the content security policy, for PNG export

Date: 2026-11-18
Status: accepted (orchestrator choice, phase 4, Q-7, at the product owner's direction of 2026-11-18)

## Decision

The content security policy keeps every directive at the application's own origin and adds `data:` and `blob:` to `img-src` only. PNG export (D3) renders the Bekijken view to an image in the browser with a DOM-to-image library, which needs those two sources; neither reaches any host. If the library needs any further relaxation (such as inline styles), that is a finding for the product owner in D3, not a change made in code.

## Motivation

FR-087 renders the PNG from the HTML view (ADR 0015); doing that in the browser needs in-memory images. The relaxation opens no connection, so H1 and ADR 0027 hold. Rejected: an own canvas renderer for the PNG (a third renderer of the canvas and its overlays, to keep equal to the HTML view).
