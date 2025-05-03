#!/usr/bin/env bash
set -e  # stops script on first error

for dir in packages/*; do
  dirname=$(basename "$dir")
  if [ -d "$dir/src" ]; then
    echo "🔍 Exporting sources to Markdown in $dirname"
    code2prompt "$dir/src" -o "tmp/$dirname.md"
  fi
done
