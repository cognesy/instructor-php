#!/usr/bin/env bash
set -euo pipefail

if (($# != 1)); then
  echo "Usage: $0 <source-sha>" >&2
  exit 2
fi

source_sha="$1"
mintlify_base="${MINTLIFY_BASE_URL:-https://docs.instructorphp.com}"
mkdocs_base="${MKDOCS_BASE_URL:-https://cognesy.github.io/instructor-php}"
attempts="${DOCS_VERIFY_ATTEMPTS:-60}"
interval="${DOCS_VERIFY_INTERVAL:-10}"

if [[ ! "$source_sha" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Source SHA must be a full 40-character Git commit hash." >&2
  exit 2
fi

read_provenance() {
  local base_url="$1"
  local deployment_url="${base_url%/}/deployment.json"
  local payload=""

  if [[ "$deployment_url" == http://* || "$deployment_url" == https://* ]]; then
    deployment_url="${deployment_url}?source=${source_sha}"
  fi

  if ! payload="$(curl -fsSL "$deployment_url" 2>/dev/null)"; then
    echo "unavailable"
    return
  fi

  php -r '$data = json_decode(stream_get_contents(STDIN), true); echo is_string($data["sourceSha"] ?? null) ? $data["sourceSha"] : "invalid";' <<< "$payload"
}

for ((attempt = 1; attempt <= attempts; attempt++)); do
  mintlify_sha="$(read_provenance "$mintlify_base")"
  mkdocs_sha="$(read_provenance "$mkdocs_base")"

  if [[ "$mintlify_sha" == "$source_sha" && "$mkdocs_sha" == "$source_sha" ]]; then
    echo "Both documentation sites expose $source_sha."
    exit 0
  fi

  if ((attempt < attempts)); then
    echo "Waiting for $source_sha (attempt $attempt/$attempts; Mintlify: $mintlify_sha; GitHub Pages: $mkdocs_sha)."
    sleep "$interval"
  fi
done

echo "Documentation sites did not converge on $source_sha (Mintlify: $mintlify_sha; GitHub Pages: $mkdocs_sha)." >&2
exit 1
