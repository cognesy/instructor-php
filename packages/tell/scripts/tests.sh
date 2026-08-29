#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
MONOREPO_DIR="$(cd "$PACKAGE_DIR/../.." && pwd)"

if [[ -f "$MONOREPO_DIR/vendor/bin/pest" ]]; then
    PEST="$MONOREPO_DIR/vendor/bin/pest"
    AUTOLOAD="$MONOREPO_DIR/vendor/autoload.php"
else
    PEST="$PACKAGE_DIR/vendor/bin/pest"
    AUTOLOAD="$PACKAGE_DIR/vendor/autoload.php"
fi

export TELL_TEST_AUTOLOAD="$AUTOLOAD"
exec php "$PEST" --configuration="$PACKAGE_DIR/phpunit.xml" "$PACKAGE_DIR/tests" "$@"
