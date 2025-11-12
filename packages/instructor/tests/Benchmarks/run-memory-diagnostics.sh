#!/bin/bash

# Memory Diagnostics Runner
# Quick launcher for memory profiling tools

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo ""
echo "════════════════════════════════════════════════════════════════════"
echo " Instructor Memory Diagnostics"
echo "════════════════════════════════════════════════════════════════════"
echo ""

# Check PHP version
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "PHP Version: $PHP_VERSION"
echo ""

# Parse arguments
TEST="${1:-all}"

case "$TEST" in
    all)
        echo "Running: All diagnostics"
        php MemoryDiagnostics.php all
        ;;
    sync-stream|compare)
        echo "Running: Sync vs Stream comparison"
        php MemoryDiagnostics.php sync-stream
        ;;
    layers|layer)
        echo "Running: Layer isolation"
        php MemoryDiagnostics.php layers
        ;;
    pipeline|checkpoints)
        echo "Running: Pipeline checkpoints"
        php MemoryDiagnostics.php pipeline
        ;;
    standalone)
        echo "Running: Standalone memory analyzer"
        php MemoryAnalyzer.php
        ;;
    help|--help|-h)
        echo "Usage: ./run-memory-diagnostics.sh [TEST]"
        echo ""
        echo "Tests:"
        echo "  all           Run all diagnostics (default)"
        echo "  sync-stream   Sync vs Stream memory comparison"
        echo "  layers        Layer isolation (which component uses memory)"
        echo "  pipeline      Pipeline memory checkpoints"
        echo "  standalone    Detailed standalone analyzer"
        echo "  help          Show this help"
        echo ""
        exit 0
        ;;
    *)
        echo "Unknown test: $TEST"
        echo "Run './run-memory-diagnostics.sh help' for usage"
        exit 1
        ;;
esac

echo ""
echo "════════════════════════════════════════════════════════════════════"
echo " ✓ Complete"
echo "════════════════════════════════════════════════════════════════════"
echo ""
