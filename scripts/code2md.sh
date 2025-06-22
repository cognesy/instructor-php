#!/usr/bin/env bash
set -e  # stops script on first error

TMP_CODE_DIR="./tmp/code"

echo "🔧 Exporting sources to Markdown"

echo "🗑️ Cleaning up old files"

rm -rf $TEMP_CODE_DIR/*

for dir in packages/*; do
  dirname=$(basename "$dir")
  if [ -d "$dir/src" ]; then
    echo "🔍 Exporting sources to Markdown in $dirname"
    code2prompt "$dir/src" -o "$TEMP_CODE_DIR/$dirname.md"
  fi
done

echo "📦 Exporting sub-package sources to Markdown"

code2prompt "packages/utils/src/JsonSchema" -o "$TEMP_CODE_DIR/cut-util-json-schema.md"
code2prompt "packages/utils/src/Messages" -o "$TEMP_CODE_DIR/cut-util-messages.md"
code2prompt "packages/polyglot/src/Inference" -o "$TEMP_CODE_DIR/cut-poly-inference.md"
code2prompt "packages/polyglot/src/Embeddings" -o "$TEMP_CODE_DIR/cut-poly-embeddings.md"

echo "📦 Making cut-down Markdown versions of selected packages"

# MAKE POLYGLOT WITH LIMITED NUMBER OF DRIVERS
mkdir -p $TEMP_CODE_DIR/polyglot-tmp
mkdir -p $TEMP_CODE_DIR/tmp1
cp -rf "./packages/polyglot/src/"* "$TEMP_CODE_DIR/polyglot-tmp/"
# remove everything under tmp/polyglot-tmp/Inference/Drivers/* except ./OpenAI and ./Gemini
mv "$TEMP_CODE_DIR/polyglot-tmp/Inference/Drivers/OpenAI" "$TEMP_CODE_DIR/tmp1"
mv "$TEMP_CODE_DIR/polyglot-tmp/Inference/Drivers/Gemini" "$TEMP_CODE_DIR/tmp1"
rm -rf $TEMP_CODE_DIR/polyglot-tmp/Inference/Drivers/*
mv "$TEMP_CODE_DIR/tmp1/"* "$TEMP_CODE_DIR/polyglot-tmp/Inference/Drivers"
rm -rf $TEMP_CODE_DIR/tmp1
code2prompt "$TEMP_CODE_DIR/polyglot-tmp" -o "$TEMP_CODE_DIR/cut-poly.md"
rm -rf $TEMP_CODE_DIR/polyglot-tmp

# MAKE INSTRUCTOR WITH LIMITED NUMBER OF DRIVERS
mkdir -p ./tmp/instructor-tmp
cp -rf "./packages/instructor/src/"* "$TEMP_CODE_DIR/instructor-tmp/"
rm -rf "$TEMP_CODE_DIR/instructor-tmp/Extras"
rm -rf "$TEMP_CODE_DIR/instructor-tmp/Events"
rm -rf "$TEMP_CODE_DIR/instructor-tmp/Deserialization"
rm -rf "$TEMP_CODE_DIR/instructor-tmp/Transformation"
rm -rf "$TEMP_CODE_DIR/instructor-tmp/Validation"
rm -f "$TEMP_CODE_DIR/instructor-tmp/SettingsStructuredOutputConfigProvider.php"
code2prompt "$TEMP_CODE_DIR/instructor-tmp" -o "$TEMP_CODE_DIR/cut-instructor.md"
rm -rf $TEMP_CODE_DIR/instructor-tmp

# MAKE HTTP-CLIENT WITH NO EXTRA MIDDLEWARE
mkdir -p $TEMP_CODE_DIR/http-tmp
cp -rf "./packages/http-client/src/"* "$TEMP_CODE_DIR/http-tmp/"
# remove everything under tmp/instructor-tmp/LLM/Drivers/* except ./OpenAI and ./Gemini
rm -rf "$TEMP_CODE_DIR/http-tmp/Middleware/RecordReplay"
rm -rf "$TEMP_CODE_DIR/http-tmp/Middleware/Examples"
code2prompt "$TEMP_CODE_DIR/http-tmp" -o "$TEMP_CODE_DIR/cut-http-normal.md"
rm -rf $TEMP_CODE_DIR/http-tmp

# MAKE MINIMAL VER OF HTTP-CLIENT
mkdir -p ./tmp/http-tmp
cp -rf "./packages/http-client/src/"* "$TEMP_CODE_DIR/http-tmp/"
# remove everything under tmp/instructor-tmp/LLM/Drivers/* except ./OpenAI and ./Gemini
rm -rf "$TEMP_CODE_DIR/http-tmp/Middleware/"
rm -rf "$TEMP_CODE_DIR/http-tmp/Debug/"
rm -rf "$TEMP_CODE_DIR/http-tmp/Adapters/Laravel"*
rm -rf "$TEMP_CODE_DIR/http-tmp/Adapters/Mock"*
rm -rf "$TEMP_CODE_DIR/http-tmp/Adapters/Symfony"*
rm -rf "$TEMP_CODE_DIR/http-tmp/Drivers/Laravel"*
rm -rf "$TEMP_CODE_DIR/http-tmp/Drivers/Mock"*
rm -rf "$TEMP_CODE_DIR/http-tmp/Drivers/Symfony"*
code2prompt "$TEMP_CODE_DIR/http-tmp" -o "$TEMP_CODE_DIR/cut-http-mini.md"
rm -rf $TEMP_CODE_DIR/http-tmp

## MAKE MINIMAL VER OF CONFIG RELATED CODE
#mkdir -p ./tmp/config-tmp
#cp -rf "./packages/utils/src/Config"* "$TEMP_CODE_DIR/config-tmp/"
#cp -rf "./packages/http-client/src/Config/"* "$TEMP_CODE_DIR/config-tmp/"
#cp -rf "./packages/instructor/src/Config/"* "$TEMP_CODE_DIR/config-tmp/"
#cp -rf "./packages/polyglot/src/Embeddings/Config/"* "$TEMP_CODE_DIR/config-tmp/"
#cp -rf "./packages/polyglot/src/LLM/Config/"* "$TEMP_CODE_DIR/config-tmp/"
#cp -rf "./packages/templates/src/Config/"* "$TEMP_CODE_DIR/config-tmp/"
#code2prompt "$TEMP_CODE_DIR/config-tmp" -o "$TEMP_CODE_DIR/x-config.md"
#rm -rf $TEMP_CODE_DIR/config-tmp

echo "✅ Export completed successfully!"
