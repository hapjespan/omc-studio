# 0040: WP-E0 decision: go for Tekenen, with three sub-decisions

Date: 2026-11-19
Status: accepted (product owner, 2026-11-19)

## Decision

Go for Tekenen (DT). DT still starts after D1, as the task list orders it. Three sub-decisions:

1. **FR-098.** URL strings that sit as text in the vendored drawing-library code and are never requested are allowed. The asset check lets such strings pass in vendored code and keeps failing every URL in application code and every request to an outside address. The network test (no request outside the own server) stays the hard requirement.
2. **P7.** The vertical text offset of 0.03 px over the limit in Chromium (1.03 px) is fixed with a correction per font in the adapter. The limit of 1 px stays and DT has to meet it on three engines.
3. **Audit findings.** The four high findings of `npm audit` in the library's dependency chain are accepted, owner the product owner: `image-size` (GHSA-5p2g-fcmc-qvqq and GHSA-w3rx-r6r6-pgpr, denial of service in parsers the application never reaches), `pptxgenjs`, `@open-pencil/core` and `@open-pencil/vue`. Every upgrade of the library re-tests them.

## Motivation

The runtime evidence is strong: zero outside requests in three engines under a policy that would refuse one. Rewriting vendored code to satisfy the check would change nothing in behaviour and would have to be redone at every upgrade. The 0.03 px miss is a calibration, not a limit of the library. The audited parser is reached only through a PowerPoint export that the application does not call.

The open findings of the report became tasks T374 to T378 in DT.3 and DT.5.
