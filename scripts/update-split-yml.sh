#!/usr/bin/env bash
# update-split-yml.sh - Update split.yml matrix from packages.json
# Usage: ./update-split-yml.sh [PROJECT_ROOT] [TARGET_SPLIT_YML]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="${1:-$(dirname "$SCRIPT_DIR")}"
SPLIT_YML="${2:-$PROJECT_ROOT/.github/workflows/split.yml}"

if [ ! -f "$SPLIT_YML" ]; then
    echo "Error: split.yml not found at $SPLIT_YML"
    exit 1
fi

echo "Updating split.yml matrix from packages.json..."

# Generate new matrix content
MATRIX_CONTENT=$("$SCRIPT_DIR/generate-split-matrix.sh" "$PROJECT_ROOT")

# Create backup
cp "$SPLIT_YML" "$SPLIT_YML.bak"

# Use awk to replace the matrix section
awk -v matrix_content="$MATRIX_CONTENT" '
    /^[[:space:]]*package:[[:space:]]*$/ { 
        print $0
        print matrix_content
        # Skip until we find the next job or end of matrix
        while ((getline) > 0) {
            if (/^[[:space:]]*steps:[[:space:]]*$/ || /^[[:space:]]*[a-zA-Z][^:]*:[[:space:]]*$/) {
                print $0
                break
            }
        }
        next
    }
    { print }
' "$SPLIT_YML.bak" > "$SPLIT_YML"

echo "✅ Updated split.yml matrix"
echo "📝 Backup saved as split.yml.bak"