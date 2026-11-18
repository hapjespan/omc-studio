# 0014: Work on a canvas in three modes: Invoeren, Tekenen and Bekijken

Date: 2026-11-18
Status: accepted (product owner, kickoff v1.2 and design, 2026-11-18)

## Decision

A Canvas has three modes, switched in one control in the order Invoeren, Tekenen, Bekijken, with the chosen mode in the address. Invoeren (Entry mode) edits structure and fields in a tree per Area with arrow buttons for order and a field "Hangt onder" for moving; it has no drawing surface and no dragging. Tekenen (Drawing mode) changes layout only and never structure. Bekijken (View mode) is read-only with overlays. A Reader sees only Bekijken. In code the modes are Entry, Drawing and View; the word "edit" names no mode.

## Motivation

Separating entering content from arranging the picture keeps each screen simple and keeps structure rules out of the drawing (H12). The names follow kickoff v1.2 and the design, which lead on screens and names. Rejected: Bekijk, Bewerk and Edit (addendum A1, A7), whose screen label "Edit" collides with editing structure, and editing structure on the canvas by dragging (kickoff 1.3 S4).
