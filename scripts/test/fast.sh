#!/usr/bin/env bash
# fast.sh - The fast lane: Unit + Feature + Regression.
#
# Runs the bulk of the suite in parallel (Pest + paratest) for speed, then a
# small SERIAL pass for the `Serial` testsuite — tests that are not safe under
# parallel scheduling (e.g. tests that override namespaced functions or rely on
# static/process-global hook state). Extra args are forwarded to both passes.
#
# The split is testsuite-based, not group-based, because paratest filters groups
# statically and cannot see Pest runtime groups — testsuite excludes it honors.
#
# Usage: fast.sh [extra pest args...]
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# scripts/test/ -> project root is two levels up
cd "$(dirname "$(dirname "$SCRIPT_DIR")")"

rc=0

# Parallel pass — Unit+Feature+Regression (the Serial suite's files are excluded
# from Regression in phpunit.xml, so they don't get scheduled across workers).
php -d memory_limit=1G ./vendor/bin/pest --parallel \
    --testsuite=Unit,Feature,Regression \
    --exclude-group=docs-qa "$@" || rc=1

# Serial pass — the parallel-unsafe tests, in a single process.
php -d memory_limit=512M ./vendor/bin/pest --compact \
    --testsuite=Serial "$@" || rc=1

exit $rc
