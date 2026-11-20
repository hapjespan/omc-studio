#!/bin/sh
set -eu

SUITES="tests/js/edit-engine"
PICTURE="tests/js/layout/pictureCompare.test.ts"
PACKAGES="@open-pencil/core @open-pencil/vue"

usage() {
    echo "usage: sh scripts/upgrade-open-pencil.sh <exact version> | --check" >&2
    exit 2
}

[ $# -eq 1 ] || usage

if [ "$1" = "--check" ]; then
    version=""
else
    version="$1"
    printf '%s' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$' || { echo "refused: the version must be exact, like 0.16.0" >&2; usage; }
fi

[ -d "$SUITES" ] || { echo "refused: $SUITES does not exist" >&2; exit 1; }
find "$SUITES" -name '*.test.ts' ! -name 'upgrade.test.ts' | grep -q . || { echo "refused: $SUITES holds no adapter suite besides upgrade.test.ts" >&2; exit 1; }

backup=$(mktemp -d "${TMPDIR:-.scratch}/open-pencil-upgrade.XXXXXX" 2>/dev/null || mktemp -d)
cp package.json package-lock.json "$backup"/

restore() {
    cp "$backup"/package.json "$backup"/package-lock.json .
    npm ci --no-audit --no-fund >/dev/null
    echo "refused: the adapter suite failed, package.json and package-lock.json are restored" >&2
}

if [ -n "$version" ]; then
    for package in $PACKAGES; do
        npm install --save-exact --no-audit --no-fund "$package@$version"
    done
fi

if npx vitest run "$SUITES" "$PICTURE"; then
    rm -rf "$backup"
    echo "adapter suite green on $(node -p "require('./package.json').dependencies['@open-pencil/core']")"
else
    [ -n "$version" ] && restore
    rm -rf "$backup"
    exit 1
fi
