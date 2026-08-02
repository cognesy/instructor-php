#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../../.."

fixture="$(mktemp -d "${TMPDIR:-/tmp}/docs-provenance.XXXXXX")"
trap 'rm -rf -- "$fixture"' EXIT

source_sha="0123456789abcdef0123456789abcdef01234567"
mkdir -p "$fixture/mintlify" "$fixture/mkdocs"
printf '{"sourceSha":"%s"}\n' "$source_sha" > "$fixture/mintlify/deployment.json"
printf '{"sourceSha":"%s"}\n' "$source_sha" > "$fixture/mkdocs/deployment.json"

MINTLIFY_BASE_URL="file://$fixture/mintlify" \
MKDOCS_BASE_URL="file://$fixture/mkdocs" \
DOCS_VERIFY_ATTEMPTS=1 \
DOCS_VERIFY_INTERVAL=0 \
  scripts/docs/verify-live-provenance.sh "$source_sha"

printf '{"sourceSha":"ffffffffffffffffffffffffffffffffffffffff"}\n' > "$fixture/mintlify/deployment.json"
if MINTLIFY_BASE_URL="file://$fixture/mintlify" \
  MKDOCS_BASE_URL="file://$fixture/mkdocs" \
  DOCS_VERIFY_ATTEMPTS=1 \
  DOCS_VERIFY_INTERVAL=0 \
  scripts/docs/verify-live-provenance.sh "$source_sha" >/dev/null 2>&1; then
  echo "Live provenance verifier accepted mismatched source commits." >&2
  exit 1
fi

if scripts/docs/verify-live-provenance.sh invalid >/dev/null 2>&1; then
  echo "Live provenance verifier accepted an invalid source commit." >&2
  exit 1
fi

echo "Live provenance verifier failure tests passed."
