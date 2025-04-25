#!/bin/bash
set -e  # stops script on first error

for dir in packages/*; do
  if [ -f "$dir/composer.json" ]; then
    echo "🔍 Updating dependencies in $dir"
    composer --working-dir="$dir" update --no-scripts --no-progress
  fi
done