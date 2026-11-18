# Development container

The repository root holds the delivered stack: a clean clone starts it with
`cp env.example .env` and `docker compose up -d`. This directory holds the
development container, which is not delivered and is only used on the
development host.

The host tooling is unchanged: it keeps running `docker compose up -d` in the
project folder. The local `.env` at the repository root decides which of the two
stacks that command starts, because Compose reads `COMPOSE_FILE` and
`COMPOSE_PROJECT_NAME` from it itself.

## Lines in the local `.env`

`.env` is never committed, so these lines live only on this machine. Add them to
start the development container instead of the delivered stack:

```
COMPOSE_FILE=.devcontainer/docker-compose.yml
COMPOSE_PROJECT_NAME=omc-studio
```

`COMPOSE_PROJECT_NAME` keeps the running container under the project it already
has, so its name stays `omc-studio-dev` and `docker exec -it omc-studio-dev`
keeps working. The same two lines also appear, commented out, at the top of
`docker-compose.yml` in this directory, where the setup is described.

## `DEV_*` variables

Every one of these is optional; the default applies when the line is absent. The
paths default to the Linux host layout, so a Mac host sets them in `.env`.

| Variable | Default | What it is |
| --- | --- | --- |
| `DEV_PORT` | `3000` | application port, published on the host loopback |
| `VITE_PORT` | `5173` | Vite port, published on the host loopback |
| `DEV_CLAUDE_HOME` | `/root/.claude` | agent configuration directory, mounted writable |
| `DEV_CLAUDE_JSON` | `/root/.claude.json` | agent state file, mounted writable |
| `DEV_KNOWLEDGE` | `/root/knowledge` | knowledge base, mounted at `/workspace/knowledge` |
| `DEV_GH_DIR` | `/root/.config/gh` | `gh` configuration, mounted read-only |
| `DEV_GITCONFIG` | `/root/.gitconfig` | git configuration, mounted read-only |
| `DEV_SECRETS_ENV` | `/dev/null` | optional extra env file, used where the host login cannot be shared |
| `DEV_PONYTAIL_DIR` | `/root/.config/ponytail` | task board on the host |

Give every project its own `DEV_PORT` and `VITE_PORT`, or two projects cannot
run at the same time.

## Build context

The build context is the repository root (`..`), so `.dockerignore` stays at the
root and trims the context from there. Every other relative path in
`docker-compose.yml` resolves from this directory.
