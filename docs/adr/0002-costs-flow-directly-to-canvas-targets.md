# 0002: Allocate costs directly to canvas targets, with application as an attribute

Date: 2026-11-18
Status: accepted

## Decision

A Cost record is allocated directly to a Domain, Cluster or Activity, with no intermediate layer. Application is an optional attribute of the Cost record, chosen from the Organisation's own list, not a step in the cost flow.

## Motivation

The product owner wants percentages on domain, cluster and/or activity; an intermediate technology or solution layer adds a second allocation and a second 100% rule for no current need. Keeping Application as an attribute still allows filtering by application later. Rejected: cost flows through applications or solutions. Settled by kickoff choices K2 and K3.
