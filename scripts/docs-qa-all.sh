#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DOCS_BIN="$ROOT_DIR/bin/instructor-docs"

if [[ ! -f "$DOCS_BIN" ]]; then
  echo "Error: docs CLI not found at $DOCS_BIN" >&2
  exit 1
fi

package_is_selected() {
  local package="$1"
  shift
  if [[ $# -eq 0 ]]; then
    return 0
  fi

  local selected
  for selected in "$@"; do
    if [[ "$selected" == "$package" ]]; then
      return 0
    fi
  done

  return 1
}

profile_for_package() {
  local package="$1"
  case "$package" in
    instructor) echo "instructor" ;;
    *) echo "none" ;;
  esac
}

total=0
failed=0

for docs_dir in packages/*/docs; do
  [[ -d "$docs_dir" ]] || continue

  package="${docs_dir#packages/}"
  package="${package%%/*}"

  if ! package_is_selected "$package" "$@"; then
    continue
  fi

  profile="$(profile_for_package "$package")"
  total=$((total + 1))

  echo "Running docs QA for $package (profile=$profile)"
  if ! php "$DOCS_BIN" qa --source-dir="$docs_dir" --profile="$profile"; then
    failed=$((failed + 1))
  fi
done

if [[ "$total" -eq 0 ]]; then
  if [[ "$#" -gt 0 ]]; then
    echo "No matching package docs directories found for: $*" >&2
  else
    echo "No package docs directories found." >&2
  fi
  exit 1
fi

if [[ "$failed" -gt 0 ]]; then
  echo "docs:qa finished with failures ($failed/$total packages)." >&2
  exit 1
fi

echo "docs:qa passed for all $total package(s)."

