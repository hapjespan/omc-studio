# 0037: Limit sessions to 12 hours and end them on every change of a sign-in factor

Date: 2026-11-18
Status: accepted (orchestrator choice, phase 4, Q-1 to Q-5, at the product owner's direction of 2026-11-18; the 7.5.2 exemption accepted by the product owner)

## Decision

A session ends after 120 minutes idle (FR-005) and in any case 12 hours after sign-in; 10 minutes before, a dialog asks the password (and code) to start a new period on the same page, which keeps WCAG 2.2.1 met by extension. An account may hold any number of sessions at once. Changing one's password, turning the second factor on or off, or regenerating recovery codes ends the user's other sessions. An Administrator can end every session of another user ("Sessies beëindigen"). Changing one's own e-mail address needs the current password. Users cannot list their own sessions; that is the third exemption of ADR 0025. The sign-in time is kept in the session; ending sessions deletes the user's rows in the sessions table.

## Motivation

ASVS 5.0 level 2 asks an absolute lifetime (7.3.2), a documented concurrency rule (7.1.2), termination after factor changes (7.4.3) and by administrators (7.4.5), and re-authentication before changing sensitive attributes (7.5.1). Twelve hours covers a working day with one sign-in. A session list per user (7.5.2) adds a screen for a handful of users that the Administrator action and the short idle timeout already cover. Rejected: a limit on concurrent sessions (no threat it answers here) and a session list in "Mijn account".
