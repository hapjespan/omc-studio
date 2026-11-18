# 0009: Keep supplier as free text, without a link to an Actor item

Date: 2026-11-18
Status: accepted

## Decision

The Supplier of a Cost record is free text in version 1. The optional link to an item in the Actors Area is dropped.

## Motivation

A Cost record belongs to the Organisation while an Actor item belongs to one Canvas (ADR 0006), so with several Canvases the link would point into one arbitrary Canvas. Rejected: a link per Canvas, which adds a mapping nobody asked for. Deviates from kickoff 7.1 and the `actor_element_id` column of chapter 9. Settled in grilling Q3.
