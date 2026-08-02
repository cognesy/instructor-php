#!/usr/bin/env bash
set -euo pipefail

if (($# < 2 || $# > 4)); then
  echo "Usage: $0 <artifact-directory> <source-sha> [branch] [remote]" >&2
  exit 2
fi

artifact_directory="$1"
source_sha="$2"
branch="${3:-docs-mintlify}"
remote="${4:-origin}"

if [[ ! "$source_sha" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Source SHA must be a full 40-character Git commit hash." >&2
  exit 2
fi

if [[ ! -d "$artifact_directory" ]]; then
  echo "Mintlify artifact directory does not exist: $artifact_directory" >&2
  exit 2
fi

artifact_directory="$(cd "$artifact_directory" && pwd)"
provenance_sha="$(php -r '$data = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR); echo $data["sourceSha"] ?? "";' "$artifact_directory/deployment.json")"
if [[ "$provenance_sha" != "$source_sha" ]]; then
  echo "Mintlify artifact provenance does not match source SHA." >&2
  exit 1
fi

remote_url="$(git remote get-url "$remote" 2>/dev/null || printf '%s' "$remote")"
workspace="$(mktemp -d "${TMPDIR:-/tmp}/docs-mintlify-publish.XXXXXX")"
trap 'rm -rf -- "$workspace"' EXIT

git clone --no-checkout "$remote_url" "$workspace/repository"
cd "$workspace/repository"

latest_main="$(git ls-remote origin refs/heads/main | awk '{print $1}')"
if [[ "$latest_main" != "$source_sha" ]]; then
  echo "Refusing stale documentation publish: origin/main is $latest_main, artifact is $source_sha." >&2
  exit 1
fi

if git show-ref --verify --quiet "refs/remotes/origin/$branch"; then
  git switch --create "$branch" --track "origin/$branch"
else
  git switch --orphan "$branch"
fi

git rm -r --ignore-unmatch .
cp -R "$artifact_directory/." .
git add -A

git config user.name 'github-actions[bot]'
git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
git commit -m "docs: publish $source_sha"
git push origin "HEAD:refs/heads/$branch"
