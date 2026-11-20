# 0046: Referrer-Policy is same-origin, not no-referrer

Date: 2026-11-20
Status: accepted (decision of the product owner, option A, after the delivery 1 acceptance work)

## Decision

Every response carries `Referrer-Policy: same-origin` instead of `no-referrer`. No other security header changes: the content security policy, `nosniff`, framing refusal and the cache headers stay as they were.

## Motivation

With `no-referrer` the browser sends no `Referer` header on the Inertia requests of the application, because they are ajax requests. Laravel's `back()` then falls back to the last page that was loaded as a full page. A form saved on a page that was reached through an Inertia link (opening a Canvas from its card in Overzicht, then adding a Domain or saving the mission) therefore ended on that earlier page, usually Overzicht, and the new row did not show until a full reload. This was found while writing the delivery 1 acceptance (`[GAP referrer back()]`) and probed in the running application for new and existing Canvases, Koers and Bekijken.

`same-origin` sends the `Referer` header only to the application's own origin and nothing to any other host. FR-090 (no outgoing connection) and the intent of H1 hold: the application never loads or links anything from outside, and a navigation to an outside page carries no referrer. The alternative, an explicit return path per form, would touch every controller that uses `back()` and keep the application dependent on a header it had switched off.

## Consequences

`docs/constraints.md` and FR-090 read "same-origin referrer policy". `tests/Feature/Security/HeadersTest.php` asserts the new value, and `tests/e2e/entry/referrer.spec.ts` proves on three engines that saving on an existing Canvas opened from its Overzicht card keeps the Administrator on Invoeren. The workaround step of the delivery 1 end-to-end test and the open-defect text of the acceptance document are removed.
