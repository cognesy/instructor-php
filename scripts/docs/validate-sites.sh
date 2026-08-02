#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

release=""
while (($#)); do
  case "$1" in
    --release)
      release="${2:-}"
      shift 2
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

if [[ -n "$release" && ! "$release" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
  echo "Invalid release version: $release" >&2
  exit 2
fi

source docs/tool-versions.env
source_sha="${DOCS_SOURCE_SHA:-$(git rev-parse HEAD)}"

for target in builds/docs-build builds/docs-mkdocs builds/docs-site; do
  rm -rf -- "$target"
done
rm -f -- builds/mkdocs.yml

composer docs gen:mintlify
composer docs gen:mkdocs

parity_args=()
if [[ -n "$release" ]]; then
  parity_args+=(--release "$release")
fi
php scripts/docs/verify-release-parity.php "${parity_args[@]}"
php scripts/docs/write-provenance.php "$source_sha" builds/docs-build builds/docs-mkdocs

if command -v mintlify >/dev/null 2>&1 && [[ "$(mintlify --version | tr -d '[:space:]')" == "$MINTLIFY_VERSION" ]]; then
  (cd builds/docs-build && mintlify validate)
else
  (cd builds/docs-build && npx --yes "mintlify@${MINTLIFY_VERSION}" validate)
fi

uv run --frozen mkdocs build --config-file builds/mkdocs.yml --strict --clean

expected_provenance="$(php -r '$data = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR); echo $data["sourceSha"];' builds/docs-mkdocs/deployment.json)"
built_provenance="$(php -r '$data = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR); echo $data["sourceSha"];' builds/docs-site/deployment.json)"
if [[ "$expected_provenance" != "$built_provenance" ]]; then
  echo "MkDocs provenance was not copied into the built site." >&2
  exit 1
fi

echo "Both documentation targets are valid for source SHA $source_sha."
