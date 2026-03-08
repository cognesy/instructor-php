#!/bin/bash
# 2.x resource model:
# - resources are owned by each package under packages/*/resources
# - no cross-package copying is required
set -euo pipefail

echo "Checking package-scoped resources..."

legacy_php_configs=$(find packages -type f -path '*/resources/config/*.php' ! -path 'packages/laravel/resources/config/instructor.php' | sort || true)
if [[ -n "$legacy_php_configs" ]]; then
  echo "❌ Found legacy PHP config resources (expected YAML only):"
  echo "$legacy_php_configs"
  exit 1
fi

package_count=0
resource_count=0
for package_dir in packages/*/; do
  if [[ ! -f "${package_dir}composer.json" ]]; then
    continue
  fi
  package_count=$((package_count + 1))
  if [[ -d "${package_dir}resources" ]]; then
    resource_count=$((resource_count + 1))
  fi
done

echo "✅ Resource layout is package-scoped."
echo "Packages with composer.json: ${package_count}"
echo "Packages with resources/: ${resource_count}"
echo "No copy step needed in 2.x."
