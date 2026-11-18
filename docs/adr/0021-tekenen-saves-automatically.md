# 0021: Save Tekenen automatically after every finished action

Date: 2026-11-18
Status: accepted (product owner, decision 32, 2026-11-18); amended by 0036: a confirmed Shift move is its own request in the same one-at-a-time order, and a Layout save still never changes a parent

## Decision

Tekenen has no Save button. After every finished action (a drag released, a resize, a property change, an alignment, an undo or redo) it sends the whole Layout of the Canvas with the structure version and the Layout version it last loaded or last saved successfully; the server checks it fully and writes it in one transaction. Saves go one at a time: actions that finish while a save is under way go together in the next save, with the version the previous save returned. Children sticking out of their parent are pushed back before sending, and the user is told which. A refused save returns the drawing to the last stored Layout and names the reason. The structure version rises with every structure change, the Layout version with every Layout write, including placements caused by Invoeren. The server checks the structure version first: a newer one asks the user to apply the Layout to what still exists (re-applied onto the current versions) or reload; with an equal structure version, a newer Layout version (another Administrator) reloads the drawing with a message. A failed request shows "Opmaak niet opgeslagen" with "Opnieuw proberen", and leaving Tekenen then asks to retry or discard. No intermediate state is kept in the browser.

## Motivation

The design shows "Opmaak opgeslagen" and no Save button. Sending the whole Layout keeps the server checks of addendum 6.3, including the H12 check on missing or extra Elements. The Layout version stops one Administrator's automatic saves from silently overwriting another's (kickoff 4 rules out simultaneous editing). With a save after every action, a local intermediate state adds nothing. Rejected: a Save button with a 30-second local intermediate state (addendum 4.3).
