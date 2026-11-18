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
Canvas sits in **Unknown allocation**, so the whole budget of the Organisation is
always attributed. Amounts **Roll up** from Activity to Cluster to Domain to Area to
Canvas; each Element has a **Direct amount** and a **Total amount**.

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
A calendar year in which a Canvas holds Yearly figures and Allocation sets.
_Avoid_: period, year (alone)

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
The fixed holder of a Canvas, outside every Area and shown like a Domain, that holds every Cost record without an Allocation set in that Canvas and Fiscal year.
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

**Share** (Aandeel):
The Total amount of an Element divided by the total of its Canvas, Unknown allocation included.
_Avoid_: percentage (alone), ratio

### Views and history

**Overlay** (Overlay):
A layer drawn over the Canvas: none, Modality or financial.
_Avoid_: view mode, filter

**Theme** (Thema):
The colours per Area and level of one Canvas.
_Avoid_: style, skin

**Quick entry** (Snel invoeren):
Creating many Elements at once from indented lines of text.
_Avoid_: bulk import, paste mode

**Snapshot** (Momentopname):
An immutable record of a Canvas with its figures for one Fiscal year.
_Avoid_: version, backup, export

**Audit log** (Logboek):
The record of every create, change and delete, with old and new value and who did it.
_Avoid_: history, activity log

### People

**User** (Gebruiker):
A person with a local account and one role, valid for every Organisation.
_Avoid_: account, member

**Administrator** (Beheerder):
The role that may change everything.
_Avoid_: admin, owner, editor

**Reader** (Lezer):
The role that may only view and export.
_Avoid_: viewer, guest

## Three biggest unknowns

1. **Unknown allocation as read by the orchestrator.** The product owner's answer
   replaced "not allocated" with Unknown allocation; its placement outside the Areas
   and the rule that a Fiscal year with amounts in it cannot be Finalised are
   assumptions (ADR 0008). Answered by: the product owner, at the phase 2 gate.
2. **Incident arrangement.** Who is told of a data or figures incident, within
   which window and through which channel. Answered by: the product owner, through
   separate arrangements.
3. **The shape of the first Organisation's real data.** How many Cost records,
   Fiscal years and Canvases it holds, and whether its budget lines map cleanly onto
   the four Cost kinds and onto a single Owner each. This decides whether the
   stated volume limits and the import columns fit practice. Answered by: the
   product owner, from the client's budget files.

Also open: the list of forbidden names for the reference-name check, supplied by the
product owner as a local file outside version control.
