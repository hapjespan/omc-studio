# 0024: Secure transport through loopback and an SSH tunnel, no TLS in the application

Date: 2026-11-18
Status: accepted (orchestrator choice, phase 3, confirmed by the product owner)

## Decision

The application listens on `127.0.0.1:8080` only and terminates no TLS. Users on the machine itself reach it over loopback; colleagues reach it through an SSH tunnel to that machine, so transport encryption and authentication of the machine are SSH's. Session cookies keep the Secure attribute on by default (FR-005); whether every supported browser sends a Secure cookie to `http://localhost` is verified in WP1, and the setting to switch it off exists for the browser that does not.

## Motivation

A1 and FR-089 rule out exposure to any network; certificates for `localhost` would add setup against H9 without protecting anything the tunnel does not already protect. Rejected: a TLS reverse proxy in the compose stack and listening on all interfaces.
