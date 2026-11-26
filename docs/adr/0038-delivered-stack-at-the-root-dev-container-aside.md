# 0038: Put the delivered stack at the repository root and the development container in .devcontainer

Date: 2026-11-18
Status: accepted (orchestrator choice, phase 4, Q-6, at the product owner's direction of 2026-11-18); to be proven in WP1

## Decision

The repository root holds the delivered stack: `docker-compose.yml` for the application container and `env.example` as its template, so a clean clone starts with `cp env.example .env` and `docker compose up -d` (FR-089). The development container's compose file and Dockerfile move to `.devcontainer/`, with `..` as build context and mount source; Laravel's `.env.example` is removed, so `env.example` is the only template. The host tooling is not changed: it keeps running `docker compose up -d` in the project folder, and on this machine the local, uncommitted `.env` sets `COMPOSE_FILE=.devcontainer/docker-compose.yml`, `COMPOSE_PROJECT_NAME=omc-studio` (so the running container keeps its project and name) and the `DEV_*` variables, listed in `.devcontainer/README.md`, so the same command starts the development container here. WP1 proves both routes; if either needs a change to the host tooling, WP1 stops and reports.

## Motivation

H9 and FR-089 fix the two commands at the root of a repository copy, while the development environment on the host must keep working unchanged. `COMPOSE_FILE` in `.env` is read by Compose itself, so the choice of stack lives in a file that is never committed. Rejected: delivering from a subfolder (breaks the two commands at the root) and changing the host tooling (outside this project).
