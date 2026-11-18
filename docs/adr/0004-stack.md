# 0004: Build on Laravel, Inertia, React, TypeScript, Tailwind and SQLite

Date: 2026-11-18
Status: accepted

## Decision

The application is built with Laravel, Inertia, React in TypeScript and Tailwind, bundled by Vite, stored in a SQLite file on a Docker volume and started with Docker Compose. SQL stays portable so PostgreSQL can be used through configuration.

## Motivation

It matches the product owner's way of working, and SQLite is a file that needs no administration, which keeps a clean-machine start at two commands (H9) and all data local (H1). Rejected: a separate database server for the delivered application. Settled by kickoff choice K6.
