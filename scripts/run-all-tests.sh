#!/usr/bin/env bash
set -e  # stops script on first error

for dir in packages/*; do
  if [ -f "$dir/composer.json" ]; then
    echo "🔍 Running tests in $dir"
    composer --working-dir="$dir" clear-cache
    composer --working-dir="$dir" dump-autoload
    composer --working-dir="$dir" install --no-scripts --no-progress
    composer --working-dir="$dir" test
  fi
done