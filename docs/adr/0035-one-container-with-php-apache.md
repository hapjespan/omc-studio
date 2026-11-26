# 0035: Deliver one container on the official php-apache image with one storage volume

Date: 2026-11-18
Status: accepted (orchestrator, phase 4; phase 4 gate approved by the product owner, 2026-11-18)

## Decision

The delivered image is built in two stages: Node builds the front-end assets, and the runtime is `php:8.4-apache` with `pdo_sqlite` and `gd`, debug off and the access log off. One named volume holds `storage/`: the SQLite file, uploads, logs and the application key generated at first start. The entrypoint exports the key as `APP_KEY`, runs the idempotent `app:install` and starts Apache. `make backup` copies the key with the database and uploads, because without it restored second-factor secrets cannot be decrypted. Compose builds the image locally; no registry is used. The build needs an internet connection once (base images, packages); ADR 0027 governs the running application. The 3 minutes of SC-001 count once the image is built on that machine, and the README states the one-time build time measured in WP1 (orchestrator choice, phase 4, Q-12). Compose publishes `127.0.0.1:8080:80`.

## Motivation

ADR 0023 and O-6 allow one process and one file; H9 asks two commands on a clean machine; FR-022 forbids a web access log. Apache with mod_php is the most established single-process way to serve PHP. Rejected: nginx with php-fpm under a supervisor (two processes in one container), FrankenPHP (newer, no gain at five users) and `php artisan serve` (a development server). Where the compose file and env template live is ADR 0038.
