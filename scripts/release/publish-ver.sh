#!/usr/bin/env bash
set -euo pipefail

script_directory="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(dirname "$(dirname "$script_directory")")"
cd "$project_root"

version="${1:-}"
version="${version#v}"
repository='cognesy/instructor-php'
notes_file="docs/release-notes/v${version}.mdx"

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
  echo "Provide a semantic version such as 2.6.0." >&2
  exit 2
fi

if [[ -n "$(git status --porcelain=v1 --untracked-files=all)" ]]; then
  echo "Release requires a clean index and worktree. Commit or remove all pending files first." >&2
  exit 1
fi

if [[ ! -f "$notes_file" ]]; then
  echo "Release notes file is missing: $notes_file" >&2
  exit 1
fi

if ! jq -e --arg page "release-notes/v${version}" '.. | strings | select(. == $page)' docs/mint.json >/dev/null; then
  echo "Release notes are not indexed in docs/mint.json: release-notes/v${version}" >&2
  exit 1
fi

if git rev-parse "refs/tags/v${version}" >/dev/null 2>&1; then
  echo "Tag v${version} already exists locally." >&2
  exit 1
fi

if git ls-remote --exit-code --tags origin "refs/tags/v${version}" >/dev/null 2>&1; then
  echo "Tag v${version} already exists on origin." >&2
  exit 1
fi

git fetch origin main
if [[ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ]]; then
  echo "Release checkout must exactly match origin/main before version synchronization." >&2
  exit 1
fi

echo "Synchronizing package constraints for v${version}..."
./scripts/packages/sync-ver.sh "$version"

echo "Validating final release documentation sources and artifacts..."
composer qa:docs-sites -- --release "$version"

unexpected_changes="$(git status --porcelain=v1 --untracked-files=all | awk '
  {
    path = substr($0, 4)
    if (path ~ /^builds\//) next
    if (path ~ /^packages\/[^\/]+\/composer\.json$/) next
    print
  }
')"
if [[ -n "$unexpected_changes" ]]; then
  echo "Release preparation changed files outside package composer manifests:" >&2
  echo "$unexpected_changes" >&2
  exit 1
fi

git add -- packages/*/composer.json
if ! git diff --cached --quiet; then
  git commit -m "chore(release): prepare v${version}" -- packages/*/composer.json
fi

final_sha="$(git rev-parse HEAD)"
echo "Pushing release candidate $final_sha to main..."
git push origin "HEAD:refs/heads/main"

echo "Waiting for both documentation sites to publish $final_sha..."
./scripts/release/verify-docs-deployment.sh "$final_sha" "$version"

git tag -a "v${version}" -m "Release version ${version}" "$final_sha"
git push origin "refs/tags/v${version}"

gh release create "v${version}" \
  --title "v${version}" \
  --notes-file "$notes_file" \
  --repo "$repository" \
  --verify-tag

echo "Release v${version} completed after both documentation sites verified $final_sha."
