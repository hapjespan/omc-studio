# Evidence WP1.2

## Pending host runs

- T016: `sh scripts/prove-clean-start.sh` needs Docker on the host; it appends the build time and the time to the first answer of `/` below. Not yet run.
- T017 Firefox and WebKit: not installed in the development container; run `EVIDENCE_FILE=$PWD/specs/001-omc-studio-v1/wp1-evidence.md npx playwright test tests/e2e/security/secure-cookie.spec.ts` with `APP_URL=http://localhost:8080` against the started stack where they are available (CI installs all three).

## Secure-cookie verification (T017)

Development container, PHP built-in server on `http://127.0.0.1:8099` with `SESSION_SECURE_COOKIE=true` (the delivered stack on `http://localhost:8080` was not available here):

- chromium: Secure session cookie accepted

## Clean start proof (2026-11-19)

- Build time: 7 seconds
- First answer of / after `docker compose up -d`: 2 seconds (limit 180)
- The 7 second build time was measured with a warm Docker cache, so it is not the clean-machine build time.
- The Firefox and WebKit run of the Secure-cookie test stays pending (no such browsers on the host).
