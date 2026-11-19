# Evidence D1.3: sign-in slice

- `tests/e2e/auth/first-start.spec.ts` passes on Chromium, Firefox and WebKit against the real application on `http://localhost:8080` with zero outside requests, zero CSP violations and zero axe violations.
- Chromium and Firefox accept the Secure session cookie on `http://localhost` (default `SESSION_SECURE_COOKIE=true`). WebKit (Playwright build, Linux) refuses it, so the sign-in fails there until `SESSION_SECURE_COOKIE=false` is set, the switch ADR 0024 reserves for exactly this browser. The run above used `false` for all three engines.
- Assumption for the product owner (advice): keep `true` as the default; tell Safari users on plain `http://localhost` to use the SSH tunnel with a hostname, or set `SESSION_SECURE_COOKIE=false` in `.env` for a machine used only over loopback.
