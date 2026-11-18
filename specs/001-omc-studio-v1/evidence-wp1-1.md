# Evidence WP1.1

Host proof for T001 and T002 (orchestrator, commit bc937a1, Docker Compose v5.7.0):

- The local `.env` holds `COMPOSE_FILE=.devcontainer/docker-compose.yml` and `COMPOSE_PROJECT_NAME=omc-studio`.
- `docker compose config` in the project folder gives project `omc-studio`, only service `dev`, `container_name: omc-studio-dev`, build context the project root with `dockerfile: .devcontainer/Dockerfile`, the same seven bind mounts and ports `127.0.0.1:8000` and `127.0.0.1:5173`.
- `docker compose up -d` reported `Container omc-studio-dev Running`: no recreation, mounts and ports identical before and after.
- A clean clone with `cp env.example .env`: `docker compose config` gives the delivered stack, service `app`, root `Dockerfile`, port 8080 and volume `storage`. The delivered stack was not built or started; that belongs to the first-start task (WP1.2).
- The development image was not rebuilt; a later rebuild uses the new build context.

Technical choices (one line each):

- `check-assets` exempts the W3C namespace identifiers, Vue's error reference link, the Tailwind banner and `http://localhost`, matched on host; they are identifiers or text, never fetched.
- The `@open-pencil` adapter location is `resources/js/edit-engine/` (ADR 0016).
- The forbidden-names list path is `.forbidden-names` or `FORBIDDEN_NAMES_FILE`; CI has no list, so GATE-U4 stays a visible skip there until the product owner supplies one.
- The PostgreSQL CI service is `postgres:17` with database `omc-studio`.
- Playwright browsers are cached in `.playwright-browsers/` (ignored); only Chromium ran locally, Firefox and WebKit run in CI.
- Larastan level 6 needs PHPDoc value types on the data provider in `tests/Unit/Support/FormatTest.php`.
- Negative amounts render as `-€ 1.234,56`; FR-013 does not specify the sign style.
