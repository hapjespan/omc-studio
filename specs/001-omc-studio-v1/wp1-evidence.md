# Evidence WP1.2

## Pending host runs

- T016: `sh scripts/prove-clean-start.sh` needs Docker on the host; it appends the build time and the time to the first answer of `/` below. Not yet run.
- T017 Firefox and WebKit: no longer required. The product owner judged the Chromium evidence sufficient on 2026-11-19.

## Secure-cookie verification (T017)

Development container, PHP built-in server on `http://127.0.0.1:8099` with `SESSION_SECURE_COOKIE=true` (the delivered stack on `http://localhost:8080` was not available here):

- chromium: Secure session cookie accepted

## Clean start proof (2026-11-19)

- Build time: 7 seconds
- First answer of / after `docker compose up -d`: 2 seconds (limit 180)
- The 7 second build time was measured with a warm Docker cache, so it is not the clean-machine build time.
- The Firefox and WebKit run of the Secure-cookie test is no longer required: the product owner accepted the Chromium evidence on 2026-11-19.
