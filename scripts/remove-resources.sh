#!/bin/bash
# Cleanup helper for legacy 1.x mirrored assets.
# 2.x canonical resources live under packages/*/resources.
set -euo pipefail

echo "Removing legacy mirrored resource artifacts..."

for package_dir in packages/*/; do
  if [[ ! -f "${package_dir}composer.json" ]]; then
    continue
  fi

  package_name=$(basename "$package_dir")
  echo "Processing package: ${package_name}"

  if [[ -d "${package_dir}prompts" ]]; then
    rm -rf "${package_dir}prompts"
    echo "  ✓ Removed legacy prompts directory"
  fi

  legacy_configs=$(find "${package_dir}resources/config" -maxdepth 1 -type f -name '*.php' ! -name 'instructor.php' 2>/dev/null || true)
  if [[ -n "$legacy_configs" ]]; then
    echo "$legacy_configs" | while IFS= read -r file; do
      rm -f "$file"
      echo "  ✓ Removed legacy PHP config: $file"
    done
  fi
done

echo "✅ Legacy mirrored assets removed."
