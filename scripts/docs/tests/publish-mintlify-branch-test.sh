#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../../.."

fixture="$(mktemp -d "${TMPDIR:-/tmp}/docs-branch.XXXXXX")"
trap 'rm -rf -- "$fixture"' EXIT

git init --bare "$fixture/remote.git" >/dev/null
git init -b main "$fixture/source" >/dev/null
git -C "$fixture/source" config user.name Test
git -C "$fixture/source" config user.email test@example.com
printf 'source\n' > "$fixture/source/README.md"
git -C "$fixture/source" add README.md
git -C "$fixture/source" commit -m source >/dev/null
git -C "$fixture/source" remote add origin "$fixture/remote.git"
git -C "$fixture/source" push -u origin main >/dev/null

mkdir "$fixture/artifact"
first_sha="$(git -C "$fixture/source" rev-parse HEAD)"
printf '{"sourceSha":"%s"}\n' "$first_sha" > "$fixture/artifact/deployment.json"
printf '# Docs\n' > "$fixture/artifact/index.mdx"
scripts/docs/publish-mintlify-branch.sh "$fixture/artifact" "$first_sha" docs-mintlify "$fixture/remote.git" >/dev/null

printf 'next\n' >> "$fixture/source/README.md"
git -C "$fixture/source" commit -am next >/dev/null
git -C "$fixture/source" push origin main >/dev/null
second_sha="$(git -C "$fixture/source" rev-parse HEAD)"
printf '{"sourceSha":"%s"}\n' "$second_sha" > "$fixture/artifact/deployment.json"
scripts/docs/publish-mintlify-branch.sh "$fixture/artifact" "$second_sha" docs-mintlify "$fixture/remote.git" >/dev/null

commit_count="$(git --git-dir="$fixture/remote.git" rev-list --count refs/heads/docs-mintlify)"
if [[ "$commit_count" != '2' ]]; then
  echo "Expected two linear deployment commits, found $commit_count." >&2
  exit 1
fi

# A superseded run carries a self-consistent artifact for an older commit, so the
# provenance stamp must match first_sha - otherwise the provenance guard fires
# first and the supersede path is never reached.
printf 'stale\n' >> "$fixture/artifact/index.mdx"
printf '{"sourceSha":"%s"}\n' "$first_sha" > "$fixture/artifact/deployment.json"
set +e
scripts/docs/publish-mintlify-branch.sh "$fixture/artifact" "$first_sha" docs-mintlify "$fixture/remote.git" >/dev/null 2>&1
superseded_status=$?
set -e
if [[ "$superseded_status" -eq 0 ]]; then
  echo "Publisher accepted a superseded source SHA." >&2
  exit 1
fi
if [[ "$superseded_status" -ne 3 ]]; then
  echo "Expected exit 3 (superseded, nothing published), got $superseded_status." >&2
  exit 1
fi

superseded_count="$(git --git-dir="$fixture/remote.git" rev-list --count refs/heads/docs-mintlify)"
if [[ "$superseded_count" != '2' ]]; then
  echo "Superseded publish must not touch the branch, found $superseded_count commits." >&2
  exit 1
fi

# A skipped publish must leave the publisher able to run again for the current tip.
printf '{"sourceSha":"%s"}\n' "$second_sha" > "$fixture/artifact/deployment.json"
if ! scripts/docs/publish-mintlify-branch.sh "$fixture/artifact" "$second_sha" docs-mintlify "$fixture/remote.git" >/dev/null 2>&1; then
  echo "Publisher rejected the current source SHA." >&2
  exit 1
fi

if [[ "$(git --git-dir="$fixture/remote.git" rev-list --count refs/heads/docs-mintlify)" != '3' ]]; then
  echo "Expected a third deployment commit after the retry." >&2
  exit 1
fi

# Provenance mismatch stays a hard failure (exit 1), never a skip.
printf '{"sourceSha":"%s"}\n' "$first_sha" > "$fixture/artifact/deployment.json"
set +e
scripts/docs/publish-mintlify-branch.sh "$fixture/artifact" "$second_sha" docs-mintlify "$fixture/remote.git" >/dev/null 2>&1
mismatch_status=$?
set -e
if [[ "$mismatch_status" -ne 1 ]]; then
  echo "Expected exit 1 for artifact provenance mismatch, got $mismatch_status." >&2
  exit 1
fi

echo "Mintlify deployment branch publisher tests passed."
