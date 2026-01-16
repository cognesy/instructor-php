#!/usr/bin/env bash
# Generate markdown contexts from codebase for LLM-based development tools
# Compatible with bash 3.2+ (macOS default)
set -e

# Configuration
OUTPUT_DIR="./tmp/code"
TEMP_DIR="./tmp/code-processing"

# Check dependencies
if ! command -v code2prompt &> /dev/null; then
    echo "❌ Error: 'code2prompt' command not found. Please install it first."
    echo "   Visit: https://github.com/mufeedvh/code2prompt"
    exit 1
fi

echo "🔧 Generating LLM contexts from codebase..."

# Clean up and prepare directories
echo "🗑️ Cleaning up old files..."
rm -rf "$OUTPUT_DIR" "$TEMP_DIR"
mkdir -p "$OUTPUT_DIR" "$TEMP_DIR"

# ================================
# FULL PACKAGE EXPORTS
# ================================
echo "📦 Exporting complete packages..."

for package_dir in packages/*/; do
    if [[ -d "${package_dir}src" ]]; then
        package_name=$(basename "$package_dir")
        echo "  📄 Generating: ${package_name}.md"
        code2prompt "${package_dir}src" -o "$OUTPUT_DIR/${package_name}.md"
    fi
done

# ================================
# FOCUSED SUBSYSTEM EXPORTS
# ================================
echo "🎯 Exporting focused subsystems..."

# Export subsystems using explicit definitions (bash 3.2 compatible)
export_subsystem() {
    local name="$1"
    local path="$2"
    if [[ -d "$path" ]]; then
        echo "  🎯 Generating: ${name}.md"
        code2prompt "$path" -o "$OUTPUT_DIR/${name}.md"
    else
        echo "  ⚠️  Skipping ${name}: path not found"
    fi
}

export_subsystem "utils-json-schema" "packages/utils/src/JsonSchema"
export_subsystem "utils-messages" "packages/utils/src/Messages"
export_subsystem "poly-inference" "packages/polyglot/src/Inference"
export_subsystem "poly-embeddings" "packages/polyglot/src/Embeddings"

# ================================
# CURATED PACKAGE VARIANTS
# ================================
echo "✂️  Creating curated package variants..."

# Polyglot with limited drivers (for context size management)
create_polyglot_minimal() {
    local temp_dir="$TEMP_DIR/polyglot-minimal"
    echo "  ✂️  Creating polyglot-minimal.md (OpenAI + Gemini drivers only)"

    mkdir -p "$temp_dir"
    cp -rf "packages/polyglot/src/"* "$temp_dir/"

    # Keep only OpenAI and Gemini drivers
    local drivers_dir="$temp_dir/Inference/Drivers"
    if [[ -d "$drivers_dir" ]]; then
        local keep_temp="$TEMP_DIR/keep-drivers"
        mkdir -p "$keep_temp"
        [[ -d "$drivers_dir/OpenAI" ]] && mv "$drivers_dir/OpenAI" "$keep_temp/"
        [[ -d "$drivers_dir/Gemini" ]] && mv "$drivers_dir/Gemini" "$keep_temp/"
        rm -rf "$drivers_dir"/*
        [[ -d "$keep_temp/OpenAI" ]] && mv "$keep_temp/OpenAI" "$drivers_dir/"
        [[ -d "$keep_temp/Gemini" ]] && mv "$keep_temp/Gemini" "$drivers_dir/"
        rm -rf "$keep_temp"
    fi

    code2prompt "$temp_dir" -o "$OUTPUT_DIR/polyglot-minimal.md"
    rm -rf "$temp_dir"
}

# Instructor core (without extras, events, validation, etc.)
create_instructor_core() {
    local temp_dir="$TEMP_DIR/instructor-core"
    echo "  ✂️  Creating instructor-core.md (core functionality only)"

    mkdir -p "$temp_dir"
    cp -rf "packages/instructor/src/"* "$temp_dir/"

    # Remove optional/complex subsystems
    for dir in Extras Events Deserialization Transformation Validation; do
        [[ -d "$temp_dir/$dir" ]] && rm -rf "$temp_dir/$dir"
    done

    # Remove specific complex files
    [[ -f "$temp_dir/SettingsStructuredOutputConfigProvider.php" ]] && rm -f "$temp_dir/SettingsStructuredOutputConfigProvider.php"

    code2prompt "$temp_dir" -o "$OUTPUT_DIR/instructor-core.md"
    rm -rf "$temp_dir"
}

# HTTP Client variants
create_http_variants() {
    # Normal version (without examples and record/replay)
    local temp_dir1="$TEMP_DIR/http-normal"
    echo "  ✂️  Creating http-normal.md (without debug middleware)"

    mkdir -p "$temp_dir1"
    cp -rf "packages/http-client/src/"* "$temp_dir1/"

    # Remove debug/example middleware
    [[ -d "$temp_dir1/Middleware/RecordReplay" ]] && rm -rf "$temp_dir1/Middleware/RecordReplay"
    [[ -d "$temp_dir1/Middleware/Examples" ]] && rm -rf "$temp_dir1/Middleware/Examples"

    code2prompt "$temp_dir1" -o "$OUTPUT_DIR/http-normal.md"
    rm -rf "$temp_dir1"

    # Minimal version (core functionality only)
    local temp_dir2="$TEMP_DIR/http-minimal"
    echo "  ✂️  Creating http-minimal.md (core functionality only)"

    mkdir -p "$temp_dir2"
    cp -rf "packages/http-client/src/"* "$temp_dir2/"

    # Remove all optional components
    for pattern in Middleware Debug; do
        [[ -d "$temp_dir2/$pattern" ]] && rm -rf "$temp_dir2/$pattern"
    done
    for subdir in Laravel Mock Symfony; do
        [[ -d "$temp_dir2/Adapters/$subdir" ]] && rm -rf "$temp_dir2/Adapters/$subdir"
        [[ -d "$temp_dir2/Drivers/$subdir" ]] && rm -rf "$temp_dir2/Drivers/$subdir"
    done

    code2prompt "$temp_dir2" -o "$OUTPUT_DIR/http-minimal.md"
    rm -rf "$temp_dir2"
}

# Execute curated variants
create_polyglot_minimal
create_instructor_core
create_http_variants

# ================================
# CLEANUP AND SUMMARY
# ================================
echo "🧹 Cleaning up temporary files..."
rm -rf "$TEMP_DIR"

echo ""
echo "✅ LLM context generation completed!"
echo "📁 Generated files in: $OUTPUT_DIR"
echo ""
echo "📋 Generated contexts:"
echo "   📦 Full packages: $(ls "$OUTPUT_DIR"/*.md 2>/dev/null | grep -E '/(addons|auxiliary|config|dynamic|events|experimental|http-client|hub|instructor|messages|polyglot|schema|setup|tell|templates|utils)\.md$' | wc -l | tr -d ' ') files"
echo "   🎯 Focused subsystems: $(ls "$OUTPUT_DIR"/*-*.md 2>/dev/null | grep -E '(utils-|poly-)' | wc -l | tr -d ' ') files"
echo "   ✂️  Curated variants: $(ls "$OUTPUT_DIR"/*-*.md 2>/dev/null | grep -E '(minimal|core|normal)' | wc -l | tr -d ' ') files"
echo ""
echo "💡 Use these contexts with LLM tools for:"
echo "   • Code analysis and understanding"
echo "   • Generating consistent code following project patterns"
echo "   • Debugging and troubleshooting"
echo "   • Documentation generation"
