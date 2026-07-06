#!/usr/bin/env bash
# test-changed.sh - Run the fast lanes (Unit+Feature+Regression, no Integration)
# for every package changed vs a git ref, including uncommitted changes.
# Excludes Integration so it stays a fast, keyless pre-push gate. Runs SERIALLY
# (scoped runs are small) to avoid paratest cross-file-helper fragility.
# Usage: test-changed.sh [base-ref]   (default base: main)
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# scripts/test/ -> project root is two levels up
cd "$(dirname "$(dirname "$SCRIPT_DIR")")"

BASE="${1:-main}"

pkgs=$( {
    git diff --name-only "$BASE"...HEAD -- 'packages/*'
    git diff --name-only -- 'packages/*'
    git diff --name-only --cached -- 'packages/*'
} 2>/dev/null | sed -E 's#packages/([^/]+)/.*#\1#' | sort -u)

if [ -z "$pkgs" ]; then
    echo "No changed packages vs $BASE"
    exit 0
fi

echo "Changed packages: $(echo $pkgs | tr '\n' ' ')"

rc=0
ran=0
for p in $pkgs; do
    for lane in Unit Feature Regression; do
        dir="packages/$p/tests/$lane"
        [ -d "$dir" ] || continue
        ran=1
        php -d memory_limit=512M ./vendor/bin/pest --compact "$dir" || rc=1
    done
done

[ "$ran" = 0 ] && echo "No fast-lane tests for changed packages"
exit $rc
