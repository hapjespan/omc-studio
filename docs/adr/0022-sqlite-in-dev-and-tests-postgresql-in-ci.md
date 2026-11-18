# 0022: Develop and test on SQLite, run the suite once more on PostgreSQL in CI

Date: 2026-11-18
Status: accepted (orchestrator choice, phase 3, confirmed by the product owner)

## Decision

Development, the test suite and every local check run on SQLite, the engine the application is delivered with (ADR 0012). CI runs the full suite a second time against a PostgreSQL service. The central PostgreSQL database of the development environment is not used by this project.

## Motivation

Tests must run on the engine that ships, or a green suite proves nothing about the delivered product. Kickoff K6 asks for portable SQL so PostgreSQL works through configuration; a second CI run turns that claim into evidence. Rejected: developing on the central PostgreSQL (tests the wrong engine) and SQLite only (portability unproven).
