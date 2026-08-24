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

# Probe only the release page itself. Its URL is minted by this release, so no
# CDN can hold a pre-release copy of it -- whereas /release-notes/versions is a
# long-lived URL, and Mintlify's edge served a stale copy of it for hours after
# v2.8.0 shipped (cf-cache-status: HIT, x-vercel-cache: STALE, age ~4h) despite
# the origin sending "no-store, no-cache, must-revalidate". That cache is not
# ours to purge, so gating a release on it made the gate flaky, not stricter.
#
# Nothing is lost by dropping the index grep: "every authored release note is
# present in BOTH site navigations" is already asserted deterministically
# against the build artifacts by scripts/docs/verify-release-parity.php, which
# publish-ver.sh runs (with --release) before anything is deployed. Re-checking
# that same property through a third party's cache added no assurance.
release_is_live() {
  local base_url="$1"
  local release_url="$2"

  curl -fsSL "${base_url}${release_url}?source=${source_sha}" | grep -F "v${version}" >/dev/null
}

DOCS_VERIFY_ATTEMPTS="$attempts" \
DOCS_VERIFY_INTERVAL="$interval" \
  ./scripts/docs/verify-live-provenance.sh "$source_sha"

mintlify_base="${MINTLIFY_BASE_URL:-https://docs.instructorphp.com}"
mkdocs_base="${MKDOCS_BASE_URL:-https://cognesy.github.io/instructor-php}"

for ((attempt = 1; attempt <= attempts; attempt++)); do
  if release_is_live "$mintlify_base" "/release-notes/v${version}" \
    && release_is_live "$mkdocs_base" "/release-notes/v${version}/"; then
    echo "Both documentation sites expose v${version} from $source_sha."
    exit 0
  fi
  sleep "$interval"
done

echo "Documentation sites did not converge on release v${version} from $source_sha." >&2
exit 1
