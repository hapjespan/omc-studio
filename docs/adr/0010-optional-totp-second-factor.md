# 0010: Offer an optional TOTP second factor per account

Date: 2026-11-18
Status: accepted

## Decision

Delivery 1 includes an optional TOTP second factor that each User can enable, with recovery codes. An Administrator can reset another User's second factor.

## Motivation

The constitution's base slice requires authentication with optional 2FA, while the kickoff names only e-mail and password. TOTP works offline, so it does not conflict with the no-external-connection rule (H1). Rejected: SMS or e-mail codes, which need an external service. Settled in grilling Q5.
