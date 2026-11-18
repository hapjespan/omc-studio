# 0025: Target OWASP ASVS 5.0 level 2, with two recorded exemptions

Date: 2026-11-18
Status: accepted (orchestrator choice, phase 3, confirmed by the product owner)

## Decision

Security requirements are written against OWASP ASVS 5.0 at level 2 and listed per chapter in `docs/constraints.md`. Two exemptions: multi-factor authentication stays optional per account (ADR 0010) where level 2 expects it, and the application carries no TLS of its own (ADR 0024). Chapters with no attack surface in this system (V9 self-contained tokens, V10 OAuth and OIDC, V17 WebRTC) are out of scope with their reason.

## Motivation

The application holds a client's financial data and has several users with two roles, which is the profile level 2 is meant for; level 1 would leave access control and logging under-specified. Level 3 adds controls for high-value targets that a local, unexposed tool does not warrant. The exemptions follow decisions already taken: the base slice asks for optional 2FA, and A1 keeps the application off every network.
