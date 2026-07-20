#!/usr/bin/env bash
# Run each package's fast test lanes through the root dependency graph.
#
# Package manifests intentionally do not resolve the other unpublished
# monorepo packages from Packagist. Using the existing modular runner keeps
# verification deterministic and avoids creating package-local vendor trees.
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"
cd "$ROOT_DIR"

rc=0
for dir in packages/*; do
  [ -f "$dir/composer.json" ] || continue
  package="${dir#packages/}"
  echo "🔍 Running fast lanes in packages/$package"
  bash "$ROOT_DIR/scripts/test/test-package.sh" "$package" || rc=1
done

exit "$rc"
