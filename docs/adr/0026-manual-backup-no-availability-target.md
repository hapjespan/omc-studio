# 0026: Back up by hand, with no availability target

Date: 2026-11-18
Status: accepted (product owner, phase 3, 2026-11-18)

## Decision

The application has no availability target: it runs when the product owner's machine runs. Backups are made by hand with `make backup`; the README advises a weekly backup and one before every import. Recovery point: everything since the last backup. Recovery time: 15 minutes with `make restore`. There is no scheduled or automatic backup, and backup retention is the product owner's.

## Motivation

A local tool on one laptop or development server has no service level to keep, and the product owner operates the machine. A scheduler would be a second process against ADR 0023. Rejected: an automatic daily backup inside the container.
