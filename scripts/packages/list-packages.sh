#!/usr/bin/env bash
# list-packages.sh - List all packages from packages.json:
# local path, composer name, and tier.
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# scripts/packages/ -> project root is two levels up
cd "$(dirname "$(dirname "$SCRIPT_DIR")")"

jq -r '.packages[] | "\(.local)\t\(.composer_name)\t\(.tier // "-")"' packages.json \
    | column -t -s $'\t'
