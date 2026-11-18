# 0019: Map the supplied design's navigation onto the product

Date: 2026-11-18
Status: accepted (product owner, design leads on screens and names, 2026-11-18)

## Decision

The shell follows the design: a top bar with the application name, a breadcrumb Organisation / Canvas with a sample-data badge, tabs Canvas, Kostenposten, Toewijzen, Versies and Beheer, one Fiscal year selector with its state, and the user menu; a left panel per screen where the screen has one, and a detail panel on the right. Screens the design does not show map as follows: the overview opens from the application name and the Organisation in the breadcrumb, and without an open Canvas the top bar holds the tabs Overzicht and, for Administrators, Beheer; Users, Organisations, Owners, Applications, Logboek, Systeemmeldingen, full export and import, and sample-data removal sit under Beheer; sign-in and Mijn account sit outside the canvas tabs. A Reader sees the tabs Canvas (Bekijken only) and Versies. The Fiscal year selector serves every role; Kostenposten, Toewijzen and the financial overlay all read its year.

## Motivation

The design leads on screens and names, but it shows five boards, not every screen of the specification; this mapping places the rest without inventing new navigation. The left panel per screen takes the role of the constitution's sidebar. Rejected: the global sidebar of the earlier specification.
