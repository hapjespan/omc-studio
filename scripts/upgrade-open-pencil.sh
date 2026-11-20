#!/bin/sh
set -eu

cd "$(dirname "$0")/.."

SUITES="tests/js/edit-engine"
PICTURE="tests/js/layout/pictureCompare.test.ts"

usage() {
    echo "usage: sh scripts/upgrade-open-pencil.sh <exact version> | --check" >&2
    exit 2
}

[ $# -eq 1 ] || usage

version=""
if [ "$1" != "--check" ]; then
    version="$1"
    case "$version" in
        *[!0-9.]* | *..* | .* | *. | "") echo "refused: the version must be exact, like 0.16.0" >&2; usage ;;
    esac
    [ "$(printf '%s' "$version" | tr -cd '.' | wc -c)" -eq 2 ] || { echo "refused: the version must be exact, like 0.16.0" >&2; usage; }
fi

[ -d "$SUITES" ] || { echo "refused: $SUITES does not exist" >&2; exit 1; }
[ -f "$PICTURE" ] || { echo "refused: $PICTURE does not exist" >&2; exit 1; }
find "$SUITES" -name '*.test.ts' ! -name 'upgrade.test.ts' | grep -q . || { echo "refused: $SUITES holds no adapter suite besides upgrade.test.ts" >&2; exit 1; }

if [ -n "$version" ]; then
    backup=$(mktemp -d "${TMPDIR:-.}/open-pencil-upgrade.XXXXXX")
    cp package.json package-lock.json "$backup"/
    restore() {
        cp "$backup"/package.json "$backup"/package-lock.json .
        npm ci --no-audit --no-fund >/dev/null || echo "restore incomplete, copies kept in $backup" >&2
        echo "refused: the upgrade failed, package.json and package-lock.json are restored" >&2
    }
    trap 'restore; exit 1' EXIT INT TERM
    npm install --save-exact --no-audit --no-fund "@open-pencil/core@$version" "@open-pencil/vue@$version"
fi

npx vitest run "$SUITES" "$PICTURE"

if [ -n "$version" ]; then
    trap - EXIT INT TERM
    rm -rf "$backup"
fi
echo "adapter suite green on $(node -p "require('./package.json').dependencies['@open-pencil/core']")"
