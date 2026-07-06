#!/usr/bin/env bash
# test-package.sh - Run the fast lanes (Unit+Feature+Regression, no Integration)
# for a single package.
#
# Runs SERIALLY on purpose: a single package is small, so serial is still ~1-2s,
# and it avoids paratest's worker-distribution fragility with cross-file Pest
# helpers (helpers defined in one test file and used in another). The full-suite
# fast lane (`just test`) is parallel; scoped runs favor reliability.
#
# Usage: test-package.sh <package> [extra pest args...]
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# scripts/test/ -> project root is two levels up
cd "$(dirname "$(dirname "$SCRIPT_DIR")")"

PKG="${1:-}"
if [ -z "$PKG" ]; then
    echo "Usage: $0 <package> [pest args...]" >&2
    exit 2
fi
shift || true

rc=0
ran=0
for lane in Unit Feature Regression; do
    dir="packages/$PKG/tests/$lane"
    [ -d "$dir" ] || continue
    ran=1
    php -d memory_limit=512M ./vendor/bin/pest --compact "$dir" "$@" || rc=1
done

if [ "$ran" = 0 ]; then
    dir="packages/$PKG/tests"
    if [ ! -d "$dir" ]; then
        echo "No tests directory for package '$PKG'" >&2
        exit 1
    fi
    php -d memory_limit=512M ./vendor/bin/pest --compact "$dir" "$@" || rc=1
fi

exit $rc
