#!/bin/sh
set -eu

root=$(cd "$(dirname "$0")/.." && pwd)
evidence=${EVIDENCE_FILE:-$root/specs/001-omc-studio-v1/wp1-evidence.md}
workdir=${PROOF_WORKDIR:-$root/.scratch}
limit=${ANSWER_LIMIT_SECONDS:-180}

mkdir -p "$workdir"
clone=$(mktemp -d "$workdir/clean-start.XXXXXX")
git clone --quiet "$root" "$clone/repo"
cd "$clone/repo"
cp env.example .env

unset COMPOSE_FILE
export COMPOSE_PROJECT_NAME=omc-studio-proof
trap 'docker compose down --volumes >/dev/null 2>&1 || true; rm -rf "$clone"' EXIT

start=$(date +%s)
docker compose build
built=$(date +%s)
docker compose up -d
until curl --fail --silent --output /dev/null http://127.0.0.1:8080/; do
    if [ $(($(date +%s) - built)) -gt "$limit" ]; then
        echo "no answer from / within $limit seconds" >&2
        exit 1
    fi
    sleep 1
done
answered=$(date +%s)

{
    printf '\n## Clean start proof (%s)\n\n' "$(date -u +%Y-%m-%d)"
    printf -- '- Build time: %s seconds\n' $((built - start))
    printf -- '- First answer of / after `docker compose up -d`: %s seconds (limit %s)\n' $((answered - built)) "$limit"
} >> "$evidence"
