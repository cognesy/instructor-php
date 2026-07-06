#!/usr/bin/env bash
set -e  # stops script on first error

for dir in packages/*; do
  if [ -f "$dir/composer.json" ]; then
    echo "🔍 Removing composer caches and ./vendor/* in $dir"
    composer --working-dir="$dir" clear-cache
    composer --working-dir="$dir" dump-autoload
    # Remove vendor directory contents
    rm -rf "$dir/vendor/"*
  fi
done
