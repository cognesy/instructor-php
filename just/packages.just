# Monorepo package management — driven by the central packages.json.

# List all packages: local path, composer name, tier.
[group('packages'), doc('List packages from packages.json')]
pkg-list:
    bash ./scripts/packages/list-packages.sh

# Install composer deps for every package.
[group('packages'), doc('composer install in every package')]
pkg-install-all:
    bash ./scripts/packages/composer-install-all.sh

# Update composer deps for every package.
[group('packages'), doc('composer update in every package')]
pkg-update-all:
    bash ./scripts/packages/composer-update-all.sh

# Clean composer caches + vendor for every package.
[group('packages'), doc('Clean caches + vendor in every package')]
pkg-clean:
    bash ./scripts/packages/clean-composer.sh

# Scaffold a new package from a JSON config (see package-config.example.json).
[group('packages'), doc('Scaffold a new package from a JSON config')]
pkg-new config:
    php ./scripts/packages/make-package {{config}}

# Scaffold a new package with the enhanced generator.
[group('packages'), doc('Scaffold a new package (enhanced generator)')]
pkg-new-enhanced config:
    php ./scripts/packages/make-package-enhanced {{config}}

# Sync internal version constraints across all packages (no tag/commit).
[group('packages'), doc('Sync version constraints across packages')]
pkg-sync-ver version:
    bash ./scripts/packages/sync-ver.sh {{version}}

# Regenerate .github/workflows/split.yml matrix from packages.json.
[group('packages'), doc('Regenerate split.yml matrix from packages.json')]
pkg-update-split:
    bash ./scripts/packages/update-split-yml.sh

# Print the split matrix generated from packages.json.
[group('packages'), doc('Print split matrix from packages.json')]
pkg-split-matrix:
    bash ./scripts/packages/generate-split-matrix.sh
