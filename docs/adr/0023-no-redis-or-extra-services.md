# 0023: Keep sessions, cache, throttling and queue in the database or files, no Redis

Date: 2026-11-18
Status: accepted (orchestrator choice, phase 3, confirmed by the product owner)

## Decision

The delivered application is one application container with a SQLite file on a volume. Sessions, cache, sign-in throttling and any queued work use the database or local files. No Redis, mail server, search engine or other service container is added. Work that would need a queue runs synchronously within the request. There is no scheduler process: time-based clean-up runs during requests or at log rotation.

## Motivation

H9 (two commands on a clean machine), K6 and "geen aparte databaseserver" leave room for one process and one file. At 5 concurrent users (constraints L-1) a second service adds operation without any gain. Rejected: the Redis of the development environment, which is not part of the delivery.
