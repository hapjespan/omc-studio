# 0030: Build sign-in and the second factor on Laravel's primitives, without Fortify

Date: 2026-11-18
Status: accepted (orchestrator, phase 4; phase 4 gate approved by the product owner, 2026-11-18)

## Decision

Sign-in, one-time passwords, the forced password change, throttling and the optional TOTP second factor are own controllers and services on Laravel's session guard, `Hash`, `RateLimiter` and the database session store, with `pragmarx/google2fa` for TOTP and `bacon/bacon-qr-code` for an inline SVG QR code. Recovery codes are stored as bcrypt hashes in their own table; throttle keys are hashes of the e-mail address.

## Motivation

FR-006 requires hashed recovery codes and replay protection per time step, and the spec adds one-time passwords with expiry, a forced change and an Administrator reset. Rejected: Laravel Fortify, which stores recovery codes encrypted rather than hashed and whose flows would be overridden almost everywhere, and the Vue starter kit, which brings Fortify and a UI the design replaces.
