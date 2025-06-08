#!/usr/bin/env bash
set -e  # stops script on first error

echo "🔧 Exporting sources to Markdown"

echo "🗑️ Cleaning up old files"

rm -rf ./tmp/*

for dir in packages/*; do
  dirname=$(basename "$dir")
  if [ -d "$dir/src" ]; then
    echo "🔍 Exporting sources to Markdown in $dirname"
    code2prompt "$dir/src" -o "tmp/$dirname.md"
  fi
done

echo "📦 Exporting sub-package sources to Markdown"

code2prompt "packages/utils/src/JsonSchema" -o "tmp/cut-util-json-schema.md"
code2prompt "packages/utils/src/Messages" -o "tmp/cut-util-messages.md"
code2prompt "packages/utils/src/Events" -o "tmp/cut-util-events.md"
code2prompt "packages/utils/src/Config" -o "tmp/cut-util-config.md"

code2prompt "packages/polyglot/src/LLM" -o "tmp/cut-poly-llm.md"
code2prompt "packages/polyglot/src/Embeddings" -o "tmp/cut-poly-embeddings.md"

echo "📦 Making cut-down Markdown versions of selected packages"

# MAKE POLYGLOT WITH LIMITED NUMBER OF DRIVERS
mkdir -p ./tmp/polyglot-tmp
mkdir -p ./tmp/tmp1
cp -rf "./packages/polyglot/src/"* "./tmp/polyglot-tmp/"
# remove everything under tmp/polyglot-tmp/LLM/Drivers/* except ./OpenAI and ./Gemini
mv "./tmp/polyglot-tmp/LLM/Drivers/OpenAI" "./tmp/tmp1"
mv "./tmp/polyglot-tmp/LLM/Drivers/Gemini" "./tmp/tmp1"
rm -rf ./tmp/polyglot-tmp/LLM/Drivers/*
mv "./tmp/tmp1/"* "./tmp/polyglot-tmp/LLM/Drivers"
rm -rf ./tmp/tmp1
code2prompt "./tmp/polyglot-tmp" -o "./tmp/cut-poly.md"
rm -rf ./tmp/polyglot-tmp

# MAKE INSTRUCTOR WITH LIMITED NUMBER OF DRIVERS
mkdir -p ./tmp/instructor-tmp
cp -rf "./packages/instructor/src/"* "./tmp/instructor-tmp/"
# remove everything under tmp/instructor-tmp/LLM/Drivers/* except ./OpenAI and ./Gemini
rm -rf "./tmp/instructor-tmp/Extras"
rm -rf "./tmp/instructor-tmp/Events"
rm -rf "./tmp/instructor-tmp/Deserialization"
rm -rf "./tmp/instructor-tmp/Transformation"
rm -rf "./tmp/instructor-tmp/Validation"
rm -f "./tmp/instructor-tmp/SettingsStructuredOutputConfigProvider.php"
code2prompt "./tmp/instructor-tmp" -o "./tmp/cut-instructor.md"
rm -rf ./tmp/instructor-tmp

# MAKE HTTP-CLIENT WITH NO EXTRA MIDDLEWARE
mkdir -p ./tmp/http-tmp
cp -rf "./packages/http-client/src/"* "./tmp/http-tmp/"
# remove everything under tmp/instructor-tmp/LLM/Drivers/* except ./OpenAI and ./Gemini
rm -rf "./tmp/http-tmp/Middleware/RecordReplay"
rm -rf "./tmp/http-tmp/Middleware/Examples"
code2prompt "./tmp/http-tmp" -o "./tmp/cut-http-normal.md"
rm -rf ./tmp/http-tmp

# MAKE MINIMAL VER OF HTTP-CLIENT
mkdir -p ./tmp/http-tmp
cp -rf "./packages/http-client/src/"* "./tmp/http-tmp/"
# remove everything under tmp/instructor-tmp/LLM/Drivers/* except ./OpenAI and ./Gemini
rm -rf "./tmp/http-tmp/Middleware/"
rm -rf "./tmp/http-tmp/Debug/"
rm -rf "./tmp/http-tmp/Adapters/Laravel"*
rm -rf "./tmp/http-tmp/Adapters/Mock"*
rm -rf "./tmp/http-tmp/Adapters/Symfony"*
rm -rf "./tmp/http-tmp/Drivers/Laravel"*
rm -rf "./tmp/http-tmp/Drivers/Mock"*
rm -rf "./tmp/http-tmp/Drivers/Symfony"*
code2prompt "./tmp/http-tmp" -o "./tmp/cut-http-mini.md"
rm -rf ./tmp/http-tmp

## MAKE MINIMAL VER OF CONFIG RELATED CODE
#mkdir -p ./tmp/config-tmp
#cp -rf "./packages/utils/src/Config"* "./tmp/config-tmp/"
#cp -rf "./packages/http-client/src/Config/"* "./tmp/config-tmp/"
#cp -rf "./packages/instructor/src/Config/"* "./tmp/config-tmp/"
#cp -rf "./packages/polyglot/src/Embeddings/Config/"* "./tmp/config-tmp/"
#cp -rf "./packages/polyglot/src/LLM/Config/"* "./tmp/config-tmp/"
#cp -rf "./packages/templates/src/Config/"* "./tmp/config-tmp/"
#code2prompt "./tmp/config-tmp" -o "./tmp/x-config.md"
#rm -rf ./tmp/config-tmp

echo "✅ Export completed successfully!"
