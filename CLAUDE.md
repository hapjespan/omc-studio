<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Context

- Personal project: code is hosted on GitHub under Martien's own account.
- Indicia work projects are never developed in this environment; they belong on the Indicia work laptop (Mac).

## Environment

Enter this project's container with `docker exec -it {{PROJECT}}-dev claude`, never `docker compose run`: run creates a second container that misses the mounts and the container_name, and the session lands in the wrong world.

- Runs in its own container, code mounted at /workspace/omc-studio.
- Database: PostgreSQL database "omc-studio" on host "postgres" (central container on dev-net). Redis on host "redis". Credentials in .env (never commit).
- Global conventions and the knowledge base workflow are defined in ~/.claude/CLAUDE.md and apply here.

## Process — Agent Workflow Graph

Think First, Specify Second, Code Last. Uncertainty that surfaces while
implementing means the specification was insufficient: stop, grill, update the
artifact, resume. Never resolve it in code.

The full process — phase graph, gate modes, model routing, the binding
deduplication table and the compliance articles — lives in
`docs/process/workflow.md`; follow the phase file for the phase you are in.
`/start-project` initialises all of it.

A phase ends **only** by running `python3 .workflow/complete-phase.py <N>`.
A human approves a gate with `python3 .workflow/approve-gate.py <N>`.

## The 95% rule

Implement only at >= 95% confidence in the specification and approach. Below
that: stop, ask the question, and record the answer in the spec or an ADR
before writing code.

## Stack

<!-- e.g. Next.js 15, Prisma, Tailwind. Fill in after scaffolding the app. -->

## Skills

Chosen at kickoff from `skills-inventory.md`. The inventory holds no Laravel,
Inertia or React skill, so the stack itself runs on the docs (context7).

- `mattpocock-skills:tdd`: test-first is the convention in a workflow project, and the calculation module (kickoff 7.3) needs full unit tests.
- `mattpocock-skills:diagnosing-bugs`: the debugging skill for a workflow project.
- `mattpocock-skills:code-review`: always qualified; the bare name is ambiguous.
- `chrome-devtools` and `a11y-debugging`: verify screens in a real browser; keyboard operation is a requirement (kickoff 18.4).

Not installed, candidates from `autoskills` (2026-11-18): `laravel-specialist`,
`laravel-patterns`, `php-pro`, `tailwind-css-patterns`, `vite`, `accessibility`.
Skipped: `frontend-design` (off in project sessions) and `seo` (local app, H1).

## Commands

<!-- e.g. npm run dev / npm test / npm run build. Fill in after scaffolding. -->

## Index

- `specs/` — what is being built: kickoff.md, PROFILE.md, spec and tasks
- `docs/adr/` — every architecture and tooling decision, one file each
  - 0001 automatic canvas layout; 0002 costs flow directly to canvas targets;
    0003 one element tree for every area; 0004 stack; 0005 integer basis points
    and cents; 0006 cost records per organisation, allocations per canvas;
    0007 finalise fiscal years per canvas; 0008 Unknown allocation as a system Domain
    in Supporting activities; 0009 supplier is free text; 0010 optional TOTP;
    0011 fiscal years per organisation, finalised per canvas (proposed)
- `docs/CONTEXT.md` — domain narrative, glossary, open unknowns
- `specs/grilling-phase-1.md` — phase 1 grilling output, feeds phase 2
- `specs/001-omc-studio-v1/`: spec.md and pii-inventory.md of version 1 (phase 2)
- `docs/process/workflow.md` — the phase graph and all its rules
- `.workflow/state.json` — current phase and gate state (never edit by hand)

## Project-specific rules

<!-- Only rules unique to this project. Everything reusable belongs in ~/.claude/CLAUDE.md or the knowledge base. -->

## House conventions

Binding, enforced by the gate review; the full articles live in
`docs/process/00-profile-and-constitution.md`: OOP with English identifiers
everywhere (Dutch only in the i18n messages file), an index on every table, no
code comments, no AI references anywhere in the repository, one compact file
per component. The spec starts from the base slice: data model, CRUD, auth
with optional 2FA, and the top-menu/sidebar/detail-window shell.

