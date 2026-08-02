#!/usr/bin/env bash
set -euo pipefail

if (($# != 2)); then
  echo "Usage: $0 <source-sha> <release-version>" >&2
  exit 2
fi

source_sha="$1"
version="${2#v}"
repository="${DOCS_GITHUB_REPOSITORY:-cognesy/instructor-php}"
workflow="${DOCS_WORKFLOW:-docs.yml}"
mintlify_base="${MINTLIFY_BASE_URL:-https://docs.instructorphp.com}"
mkdocs_base="${MKDOCS_BASE_URL:-https://cognesy.github.io/instructor-php}"
attempts="${DOCS_VERIFY_ATTEMPTS:-60}"
interval="${DOCS_VERIFY_INTERVAL:-10}"

if [[ ! "$source_sha" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Source SHA must be a full 40-character Git commit hash." >&2
  exit 2
fi

run_id=''
for ((attempt = 1; attempt <= attempts; attempt++)); do
  run_id="$(gh run list --repo "$repository" --workflow "$workflow" --commit "$source_sha" --limit 1 --json databaseId --jq '.[0].databaseId // empty')"
  if [[ -n "$run_id" ]]; then
    break
  fi
  sleep "$interval"
done

if [[ -z "$run_id" ]]; then
  echo "No documentation workflow run found for $source_sha." >&2
  exit 1
fi

gh run watch "$run_id" --repo "$repository" --exit-status

provenance_matches() {
  local base_url="$1"
  curl -fsSL "${base_url}/deployment.json?source=${source_sha}" \
    | php -r '$data = json_decode(stream_get_contents(STDIN), true); exit(($data["sourceSha"] ?? "") === $argv[1] ? 0 : 1);' "$source_sha"
}

release_is_live() {
  local base_url="$1"
  local release_url="$2"
  local index_url="$3"

  curl -fsSL "${base_url}${release_url}?source=${source_sha}" >/dev/null \
    && curl -fsSL "${base_url}${index_url}?source=${source_sha}" | grep -Fq "v${version}"
}

for ((attempt = 1; attempt <= attempts; attempt++)); do
  if provenance_matches "$mintlify_base" \
    && provenance_matches "$mkdocs_base" \
    && release_is_live "$mintlify_base" "/release-notes/v${version}" "/release-notes/versions" \
    && release_is_live "$mkdocs_base" "/release-notes/v${version}/" "/release-notes/versions/"; then
    echo "Both documentation sites expose v${version} from $source_sha."
    exit 0
  fi
  sleep "$interval"
done

echo "Documentation sites did not converge on release v${version} from $source_sha." >&2
exit 1
