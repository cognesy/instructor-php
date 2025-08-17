#!/usr/bin/env bash
set -e  # stops script on first error

for dir in packages/*; do
  if [ -f "$dir/composer.json" ]; then
    echo "🔍 Running psalm in $dir"
    composer --working-dir="$dir" psalm
  fi
done