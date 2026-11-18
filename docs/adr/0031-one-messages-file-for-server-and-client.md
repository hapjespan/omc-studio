# 0031: Keep every Dutch text in one file, lang/nl.json, read by server and client

Date: 2026-11-18
Status: proposed (orchestrator, phase 4); confirmation at the phase 4 gate

## Decision

`lang/nl.json` is the only file holding Dutch. PHP reads it with `__()`, the Vue app imports the same file at build time and reads it with one `t(key, params)` function. Keys are English. Area and level labels, Modality names, "Leeg canvas", "Onbekende allocatie", CSV headers and every refusal message are keys in it; the seed writes no Dutch of its own.

## Motivation

House convention 1 allows Dutch in exactly one place, and refusals made on the server must read the same as labels in the browser. Rejected: `messages/nl.ts` for the client beside Laravel's `lang/nl/*.php` for the server (two files), and a server that returns only keys (the console command and container log also print Dutch).
