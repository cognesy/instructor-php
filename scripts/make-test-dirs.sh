#!/bin/bash

echo "Creating test directories in all packages..."

for dir in packages/*; do
  if [ -d "$dir" ]; then
    echo "Creating test directories in $dir"
    echo " - Creating Feature, Integration, and Unit test directories"
    mkdir -p "$dir/tests/Feature"
    mkdir -p "$dir/tests/Integration"
    mkdir -p "$dir/tests/Unit"
    echo " - Creating .gitkeep files"
    touch "$dir/tests/Feature/.gitkeep"
    touch "$dir/tests/Unit/.gitkeep"
    touch "$dir/tests/Integration/.gitkeep"
  fi
done

echo "Test directories created successfully."
