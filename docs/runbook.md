# Runbook

Audience: the product owner, who runs the machine, and the Administrators of the application. Risk class is standard (specs/PROFILE.md); the incident plan below follows docs/constraints.md section 8.

## Run

- Start: `cp env.example .env`, then `docker compose up -d`; open http://localhost:8080. Stop: `docker compose down` (the data volume stays).
- Never use `docker compose down -v` or remove the volume: that deletes the data. Restore only through `make restore FOLDER=<folder>`.
- Upgrade: take `make backup`, pull the new version, `docker compose up -d --build`.
- Lost administrator password: `docker compose exec -u www-data app php artisan app:reset-admin-password admin@localhost` (see README).

## Observe

- System notices: an unseen notice puts a warning in the top bar of every Administrator; mark it "Gezien" after handling.
- Security log: `storage/logs/security-*.log`, 90 days. Error log: `storage/logs`, 14 days. Neither is in the backup; copy them with `docker compose cp app:/var/www/html/storage/logs .` when needed.
- Health: the login page loads at http://localhost:8080.

## Incident

Detection: a System notice (failed closing check, damaged Snapshot, 10 lockouts within 60 minutes).

1. The warned Administrator tells the product owner, with the notice text and time.
2. The product owner starts the ISO notification policy of their organisation, which sets the windows, parties and channels. The application names none of its own.
3. Reconstruct with the Audit log (what and who), the security log (sign-ins and refusals) and a backup of the date in question.
4. Contain: end sessions of the affected user (user administration), reset the password, restore from backup if data is damaged.

## Roll back

A release is a git commit on `main`. Roll back with `git checkout <previous tag or commit>`, `docker compose up -d --build`, then `make restore` of the backup taken before the upgrade if the database changed.

## Deployment

`deploy_target: none`: the project is deliberately not deployed to a platform; it is delivered as the Docker stack in this repository and run on the product owner's machine. This would stop being true only if the product owner asks for a hosted instance, which needs a new ADR (see ADR 0023, 0024 and 0027) and a target recorded in specs/PROFILE.md.
