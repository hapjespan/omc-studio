# OMC Studio, domain context

## The problem

An organisation wants to see what it does and what that costs, in one picture. Today
its operating model lives in a hand-filled tool, and its budget lives in spreadsheets,
with no reliable link between the two. The product owner, who is not a developer,
needs to lay out the organisation's operating model without help: its direction,
the activities it performs grouped into domains, and the actors, channels and value
propositions around them, and then to attribute every budget line, every year,
in full to the parts of that model it pays for, so that each domain shows what it
costs and the totals always close to the cent. The model and its figures are client
financial data and must never leave the machine they are kept on. The first
organisation is a childcare and primary education group of about 35 locations; the
same work must serve other organisations later.

## Narrative

An **Organisation** owns **Canvases** and **Cost records**. A Canvas has eight fixed
**Areas**. The Direction area holds **Statements**: a mission, a vision and strategic
goals. Every other Area holds a tree of **Elements**: **Domains** directly under the
Area, optional **Clusters** inside a Domain, and **Activities** under a Domain or a
Cluster. In list Areas (Actors, Channels, Value propositions) the same tree is
labelled **Group** and **Item**, and Clusters do not exist there. Domains, Clusters
and Activities in activity Areas can carry a **Modality** with a **Rationale**.

A Cost record has **Yearly figures** per **Fiscal year**: a **Budget**, a
**Forecast**, a **Run** and **Change** share and FTE. Per Canvas and Fiscal year, a
Cost record has either no **Allocation set** or one whose **Allocation lines** add up
to exactly 100% over **Targets**. Whatever a Cost record does not allocate in a
Canvas sits in **Unknown allocation**, a fixed Domain the system keeps in Supporting
activities, so the whole budget of the Organisation is always attributed. Amounts **Roll up** from Activity to Cluster to Domain to Area to
Canvas; each Element has a **Direct amount** and a **Total amount**.

A Canvas is worked on in three modes. In **Entry mode** an Administrator builds the
structure and fills in fields, Area by Area, as a tree. In **Drawing mode** they
arrange the picture: the **Layout** of each Element, its place, size and **Element
style** on the **Artboard**, without touching structure, except that one Cluster, Activity or Item can be moved to another parent in the same kind of Area by dragging it with Shift and confirming (ADR 0036). In **View mode** anyone
reads the Canvas with its overlays. Every Canvas starts in **Automatic layout** and
can always go back to it; an Element changed in Drawing mode keeps its **Manual
layout**. Where an Element has no style of its own, the **Default colours** apply.

A Fiscal year of a Canvas can be **Finalised**, which makes it read-only until it is
**Reopened**. Removing an Element that carries allocations requires
**Redistribution** first, after which the Element is **Archived**. A **Snapshot**
freezes a Canvas with its figures for one Fiscal year, forever.

## Glossary

Code uses the English term. The Dutch screen label lives only in the messages file.

### Organisation and canvas

**Organisation** (Organisatie):
A client whose operating model and budget are recorded.
_Avoid_: tenant, company, client

**Canvas** (Canvas):
One operating model picture of an Organisation, made of the eight Areas.
_Avoid_: board, model, diagram

**Template** (Sjabloon):
A Canvas holding only structure and Statements, used as the starting point of a new Canvas.
_Avoid_: blueprint, preset

**Area** (Area):
One of eight fixed regions of every Canvas, of kind text, activities or list.
_Avoid_: section, zone, lane

**Direction** (Koers):
The text Area holding the Statements.
_Avoid_: course, strategy block

**Statement** (Missie, Visie, Strategisch doel):
A mission, vision or strategic goal of a Canvas.
_Avoid_: goal block, text block

### Structure

**Element** (Element):
Any node of the tree inside an Area: a Domain, Cluster or Activity.
_Avoid_: node, component, block

**Domain** (Domein; Groep in list Areas):
The top-level Element directly under an Area.
_Avoid_: category, capability

**Group** (Groep):
The screen label of a Domain in a list Area; not a separate concept.
_Avoid_: using it in code

**Cluster** (Cluster):
An optional, non-nestable bundle of Activities inside one Domain of an activity Area.
_Avoid_: subdomain, subgroup

**Activity** (Activiteit; Actor, Kanaal, Propositie in list Areas):
What the Organisation does and its result, without the how; always under a Domain or a Cluster.
_Avoid_: process, task, function

**Item** (Item):
The screen label of an Activity in a list Area; not a separate concept.
_Avoid_: using it in code

**Modality** (Modaliteit):
One of four fixed classifications of an Element: Common, Adaptive, Specialized, Distinct.
_Avoid_: type, category

**Rationale** (Onderbouwing):
The written reason for an Element's Modality.
_Avoid_: justification, note

**Archived** (Gearchiveerd):
Hidden from daily use but kept, so Snapshots and history stay readable.
_Avoid_: soft-deleted, inactive

### Money

**Cost record** (Kostenpost):
One budget line of an Organisation: a contract, staff, a project or other.
_Avoid_: cost item, budget line, expense

**Cost kind** (Soort):
The kind of a Cost record: supplier contract, staff, project or other.
_Avoid_: cost type, category

**Owner** (Eigenaar):
The organisational unit or role responsible for a Cost record, never a person.
_Avoid_: responsible, manager

**Application** (Applicatie):
An entry in the Organisation's own list of applications a Cost record can refer to.
_Avoid_: system, tool

**Supplier** (Leverancier):
The free-text name of the party a Cost record pays.
_Avoid_: vendor, actor

**Fiscal year** (Boekjaar):
A calendar year of an Organisation in which its Cost records hold Yearly figures; Finalised per Canvas.
_Avoid_: period, year (alone)

**Cost centre** (Kostenplaats):
The free-text accounting code of a Cost record in the Organisation's own books.
_Avoid_: cost center code, ledger

**Reference date** (Peildatum):
The date on which the Yearly figures of a Cost record were known to be right.
_Avoid_: as-of date, snapshot date

**Yearly figures** (Jaarwaarden):
The Budget, Forecast, Run, Change, FTE, reference date and remark of one Cost record in one Fiscal year.
_Avoid_: year values, cost record year

**Budget** (Budget):
The planned amount of a Cost record in a Fiscal year.
_Avoid_: amount, cost

**Forecast** (Laatste prognose):
The latest expected amount of a Cost record in a Fiscal year.
_Avoid_: prognosis, estimate, actuals

**Run** and **Change** (Run, Change):
The shares of a Cost record that keep the Organisation running and that change it; the remainder is Undetermined.
_Avoid_: opex, capex

**Undetermined** (Niet bepaald):
The share of a Cost record that is neither Run nor Change.
_Avoid_: other, rest

**Finalised** (Definitief):
The state of a Fiscal year of a Canvas whose figures are read-only.
_Avoid_: closed, locked, final

**Reopen** (Heropenen):
Taking a Finalised Fiscal year back to editable.
_Avoid_: unlock, unfinalise

**Carry over** (Overnemen):
Copying Yearly figures and Allocation sets from one Fiscal year into the next.
_Avoid_: roll forward, copy year

### Allocation

**Target** (Doel):
An active Domain, Cluster or Activity in an activity Area that a Cost record can be allocated to.
_Avoid_: destination, bucket

**Allocation set** (Toewijzingsset):
All Allocation lines of one Cost record in one Canvas and Fiscal year, adding up to exactly 100%.
_Avoid_: distribution, split

**Allocation line** (Toewijzingsregel):
One Target with its percentage within an Allocation set.
_Avoid_: allocation (alone), assignment

**Unknown allocation** (Onbekende allocatie):
The fixed, system-managed Domain of every Canvas in Supporting activities that holds every Cost record without an Allocation set in that Canvas and Fiscal year; it is not a Target and cannot be renamed, moved or deleted.
_Avoid_: unassigned, unallocated, niet toegewezen

**Redistribution** (Herverdelen):
Moving the Allocation lines of an Element that is being removed to another Target, or sending the affected Cost records back to Unknown allocation.
_Avoid_: reallocation, transfer

**Direct amount** (Direct):
The amount allocated to an Element itself.
_Avoid_: own amount

**Total amount** (Totaal):
The Direct amount of an Element plus everything below it.
_Avoid_: rolled-up amount, sum

**Roll up** (Oprollen):
Adding amounts from Activity to Cluster to Domain to Area to Canvas.
_Avoid_: aggregate, consolidate

**Closing check** (Sluitcontrole):
The check, after every calculation, that the figures of a Canvas add up to the cent; when it fails no figure is shown.
_Avoid_: validation, reconciliation

**System notice** (Systeemmelding):
A record of a failed Closing check, a damaged Snapshot or repeated sign-in lockouts, for the Administrator.
_Avoid_: error log, alert

**Share** (Aandeel):
The Total amount of an Element divided by the total of its Canvas.
_Avoid_: percentage (alone), ratio

### Views and history

**Overlay** (Overlay):
A layer drawn over the Canvas in View mode: none, Modality or financial.
_Avoid_: view mode (that is Bekijken), filter

**Entry mode** (Invoeren):
The mode in which an Administrator creates, changes, orders and moves Elements and their fields in a tree per Area, without a drawing surface.
_Avoid_: edit mode, editor, Bewerk

**Drawing mode** (Tekenen):
The mode in which an Administrator changes the Layout of existing Areas and Elements; the only structure change it allows is the Shift move, under the structure rules of Entry mode (ADR 0036).
_Avoid_: edit mode, Edit, design mode, editor

**Shift move** (Verplaatsen met Shift):
Moving one Cluster, Activity or Item to another parent in the same kind of Area in Drawing mode, by dragging it with Shift held or through "Verplaatsen naar", always after the confirmation "Verplaatsen naar <doel>?"; a structure change under the same rules as "Hangt onder", except that the block keeps its place, size and style.
_Avoid_: reparent (in screen text), drag-and-drop move

**View mode** (Bekijken):
The read-only mode with overlays, Snapshots and export; the only mode a Reader sees.
_Avoid_: preview, Bekijk

**Layout** (Opmaak):
Where an Element or Area sits on the Artboard, how large it is, and its Element style.
_Avoid_: drawing, design, position (alone)

**Automatic layout** (Automatische opmaak):
The Layout the application computes from the structure alone; the start of every Canvas and the fallback.
_Avoid_: default layout, auto-position

**Manual layout** (Handmatige opmaak):
The Layout of an Element or Area as last set in Drawing mode, kept until it is set back to automatic; its height is a minimum, because an Element always grows to hold its name and children.
_Avoid_: custom layout, free layout

**Element style** (Stijl):
The colours and other visual properties one Element has of its own, over the Default colours.
_Avoid_: theme, skin

**Default colours** (Standaardkleuren):
The colours per Area and level the application ships, shown wherever an Element has no Element style.
_Avoid_: theme, palette

**Artboard** (Tekenvlak):
The fixed drawing surface of one Canvas, landscape A3 at 1680 by 1188 pixels, on which every Layout is measured in whole pixels; it never grows or scales, and content that does not fit is shown beyond its edge.
_Avoid_: page, stage, canvas (alone)

**Font list** (Fontlijst):
The fixed set of fonts shipped with the application that canvas text may use.
_Avoid_: fonts (alone), typeface library

**Quick entry** (Snel invoeren):
Creating many Elements at once from indented lines of text.
_Avoid_: bulk import, paste mode

**Snapshot** (Momentopname):
An immutable record of a Canvas with its figures for one Fiscal year.
_Avoid_: version, backup, export

**Audit log** (Logboek):
The record of every create, change and delete, with old and new value and who did it.
_Avoid_: history, activity log

### Working with data

**Dry run** (Proefrun):
Checking an import file and reporting every problem in it without writing anything.
_Avoid_: preview, validation run

**Warning** (Waarschuwing):
A remark in Entry mode on something allowed but probably unfinished: an empty Cluster, a Domain without Activities, Elements that overlap on the Artboard, or a block that does not fit on the Artboard.
_Avoid_: error, notice

**Replace by** (Vervangen door):
Moving every Cost record from an Owner or Application to another one so the first can be deleted.
_Avoid_: merge, reassign

### People

**User** (Gebruiker):
A person with a local account and one role, valid for every Organisation.
_Avoid_: account, member

**Administrator** (Beheerder):
The role that may change everything.
_Avoid_: admin, owner, editor

**Second factor** (Tweede factor):
A six-digit code from an authenticator app, asked after the password when a User has turned it on.
_Avoid_: 2FA, MFA, token

**Recovery code** (Herstelcode):
A single-use code that replaces the Second factor once, when the app is not at hand.
_Avoid_: backup code

**Reader** (Lezer):
The role that may only view and export.
_Avoid_: viewer, guest

## Three biggest unknowns

Answered by the product owner at the phase 2 gate, 2026-11-18.

1. **Unknown allocation.** Decided: a fixed, system-managed Domain in Supporting
   activities (ADR 0008, accepted). Confirmed on 2026-11-18: a Fiscal year cannot be
   Finalised while any amount sits in Unknown allocation.
2. **Incident arrangement.** Decided: at an incident the product owner is warned
   and starts the ISO notification policy of their organisation, which sets
   windows and channels. The application only warns the Administrators visibly.
3. **The shape of the first Organisation's real data.** Size still unknown; the
   organisation chart shows a large landscape with two collective labour
   agreements and a governance layer. The performance limits of the kickoff stand.

Forbidden names: no list yet. The first client's name appears nowhere in the
repository; the reference-name check reads a list file outside version control
that starts with that name and can be extended.

## Future wish, outside version 1

After the picture of the whole Organisation, zooming into separate locations or
units in a deeper layer. Explicitly not in version 1 and not prepared with extra
abstractions.

## Sources and precedence

Four documents describe what is built: kickoff repo version 1.3 (`specs/kickoff.md`),
the product owner's kickoff v1.2 (`specs/kickoff-v1.2-po.md`), addendum A1
(`specs/kickoff-addendum-edit-mode.md`) and the design (`specs/design/`). The
product owner's gate answers recorded in repo version 1.3 go first; for screens and
names v1.2 and the design lead; for technique the addendum leads. Every difference
and its status is in `specs/001-omc-studio-v1/source-conflicts.md`.
