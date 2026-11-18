# 0018: Ship default colours and set colours per Element in Tekenen, without a theme screen

Date: 2026-11-18
Status: accepted (product owner, source decision c, 2026-11-18)

## Decision

There is no theme screen. The application ships default colours per Area and level, taken from the design; a Canvas shows them wherever an Element has no style of its own. Colours are changed only in Tekenen, per Element or for all Domains of an Area at once, and are stored as part of the Element's layout. Text drawn on a default colour is ink `#171B21` or white, whichever contrasts more.

## Motivation

Kickoff v1.2 and the design drop the theme screen and move colour into Tekenen; one place for colour avoids two layers of settings that can contradict each other. Consequence, accepted by the product owner (decisions 49 and 50 in `specs/001-omc-studio-v1/source-conflicts.md`): until Tekenen ships colours cannot be changed; after a no-go on the technical proof a separate decision follows, and nothing is prepared for it. Rejected: a per-Canvas Theme screen as a default under the per-Element style (addendum 6.1.4, kickoff 1.3 6.5.4).
