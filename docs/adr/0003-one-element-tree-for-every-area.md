# 0003: Use one element tree for every area, with a domain layer everywhere

Date: 2026-11-18
Status: accepted

## Decision

Every Area except Direction holds the same tree: Domain, optional Cluster, Activity. List Areas (Actors, Channels, Value propositions) use that same tree with the labels Group and Item and without Clusters. The Areas of kind activities, Richten and Beheersen included, always have a Domain layer.

## Motivation

One model and one screen mean less code, and a Domain layer everywhere follows from the rule that no Activity exists without a Domain (H3). This deviates from the reference, where Activities hang directly under those Areas. Rejected: a separate model for list Areas, and Activities directly under an Area. Settled by kickoff choices K4 and K5.
