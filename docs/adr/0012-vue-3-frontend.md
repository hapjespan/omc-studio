# 0012: Build the front end in Vue 3, not React

Date: 2026-11-18
Status: accepted (product owner, addendum A1 and source decision, 2026-11-18)

## Decision

The application is built with Laravel, Inertia and Vue 3 in TypeScript, Tailwind, bundled by Vite, stored in a SQLite file on a Docker volume and started with Docker Compose. SQL stays portable so PostgreSQL can be used through configuration. Every screen is Vue 3; there is no React anywhere. Supersedes ADR 0004.

## Motivation

The drawing library chosen for Tekenen (ADR 0016) documents only a Vue SDK. A Vue island inside a React application would mean two frameworks, two component styles and more risk; Inertia works the same with Vue. Rejected: React with a Vue island and a later fallback (kickoff v1.2 A7). The design in `specs/design/` is a React prototype and serves only as a visual reference. Settled by addendum K6 (changed) and the product owner's decision that the addendum leads on technique.
