#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
SOURCE_DIR="$REPO_ROOT/examples/C01_Http"
TARGET_DIR="$REPO_ROOT/packages/hub/examples/C01_Http"

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "Source directory not found: $SOURCE_DIR" >&2
  exit 1
fi

mkdir -p "$TARGET_DIR"

while IFS= read -r -d '' example_dir; do
  name="$(basename "$example_dir")"
  mkdir -p "$TARGET_DIR/$name"
  cp "$example_dir/run.php" "$TARGET_DIR/$name/run.php"
done < <(find "$SOURCE_DIR" -mindepth 1 -maxdepth 1 -type d -print0)

echo "Synced HTTP examples from $SOURCE_DIR -> $TARGET_DIR"
