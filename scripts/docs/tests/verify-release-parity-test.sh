#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../../.."

fixture="$(mktemp -d "${TMPDIR:-/tmp}/docs-parity.XXXXXX")"
trap 'rm -rf -- "$fixture"' EXIT

mkdir -p "$fixture/source" "$fixture/mintlify/release-notes" "$fixture/mkdocs/release-notes"
printf '# Release\n' > "$fixture/source/v1.2.3.mdx"
printf '# Release\n' > "$fixture/mintlify/release-notes/v1.2.3.mdx"
printf '# Release\n' > "$fixture/mkdocs/release-notes/v1.2.3.md"
printf '{"navigation":[{"group":"Release Notes","pages":["release-notes/v1.2.3"]}]}' > "$fixture/mintlify/mint.json"
printf 'nav:\n  - Release Notes:\n      - v1.2.3: release-notes/v1.2.3.md\n' > "$fixture/mkdocs.yml"

arguments=(
  --release 1.2.3
  --source-dir "$fixture/source"
  --mintlify-dir "$fixture/mintlify/release-notes"
  --mkdocs-dir "$fixture/mkdocs/release-notes"
  --mintlify-config "$fixture/mintlify/mint.json"
  --mkdocs-config "$fixture/mkdocs.yml"
)

php scripts/docs/verify-release-parity.php "${arguments[@]}"

rm "$fixture/mintlify/release-notes/v1.2.3.mdx"
if php scripts/docs/verify-release-parity.php "${arguments[@]}" > /dev/null 2>&1; then
  echo "Parity validator accepted a missing target page." >&2
  exit 1
fi

printf '{"navigation":[]}' > "$fixture/mintlify/mint.json"
printf '# Release\n' > "$fixture/mintlify/release-notes/v1.2.3.mdx"
if php scripts/docs/verify-release-parity.php "${arguments[@]}" > /dev/null 2>&1; then
  echo "Parity validator accepted a missing navigation entry." >&2
  exit 1
fi

echo "Release-note parity failure tests passed."
