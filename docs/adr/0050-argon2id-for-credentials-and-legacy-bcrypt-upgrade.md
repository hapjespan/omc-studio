# 0050 — Argon2id for credentials and a forced change for legacy bcrypt accounts

Date: 2026-11-27
Status: accepted

## Decision

Passwords, one-time passwords and recovery codes are hashed with Argon2id (`HASH_DRIVER=argon2id`, 64 MiB, 4 passes, 1 thread). Existing bcrypt hashes still verify, because verification is algorithm-agnostic. After a successful sign-in with a bcrypt hash (identified by the `$2y$` prefix) the account gets `must_change_password`, and the existing MustChangePassword middleware sends the user to the new-passphrase screen; the replacement is stored as Argon2id. Reuse of the current passphrase is judged on the whole passphrase, so a legacy account can extend a passphrase beyond byte 72. The dummy hash for unknown addresses is an Argon2id hash so unknown and known accounts cost the same. Recovery codes stay usable until they are regenerated.

## Motivation

bcrypt only uses the first 72 bytes while the policy accepts 12 to 128 characters, so characters beyond byte 72 protected nothing. Argon2id covers the whole passphrase. Rehashing silently at sign-in is not an option here because the plain passphrase of a legacy account may have been truncated by its own hash; a forced change is the only way to prove and store the full passphrase.
