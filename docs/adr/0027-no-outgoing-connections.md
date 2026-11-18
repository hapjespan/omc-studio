# 0027: Make no outgoing connection and use no outside service

Date: 2026-11-18
Status: accepted (kickoff H1, recorded in phase 3)

## Decision

The application opens no connection to any host other than its own: no CDN, outside fonts, telemetry, update check, error-tracking or monitoring service, mail, outside identity provider or licence server. Fonts, icons and the drawing library are bundled and pinned. The content security policy allows only the application's own origin (FR-090), and a build check fails on any outside URL in the built assets (FR-098).

## Motivation

H1 keeps every piece of data on the machine. It closes off several design options for phase 4: hosted error tracking, e-mail based password reset (SD-21), SMS or e-mail second factors (ADR 0010) and assets from a CDN. Monitoring and incident detection must therefore live inside the application (System notices, security log).
