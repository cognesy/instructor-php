#!/usr/bin/env bash
set -e  # stops script on first error

for dir in packages/*; do
  dirname=$(basename "$dir")
  if [ -d "$dir/src" ]; then
    echo "🔍 Exporting sources to Markdown in $dirname"
    code2prompt "$dir/src" -o "tmp/$dirname.md"
  fi
done

code2prompt "packages/utils/src/JsonSchema" -o "tmp/util-json-schema.md"
code2prompt "packages/utils/src/Messages" -o "tmp/util-messages.md"
code2prompt "packages/utils/src/Events" -o "tmp/util-events.md"
code2prompt "packages/utils/src/Config" -o "tmp/util-config.md"
code2prompt "packages/polyglot/src/LLM" -o "tmp/poly-llm.md"
code2prompt "packages/polyglot/src/Embeddings" -o "tmp/poly-embeddings.md"

# MAKE POLYGLOT WITH LIMITED NUMBER OF DRIVERS
cp -r "packages/polyglot/src/"* "tmp/polyglot-tmp/"
// remove everything under tmp/polyglot-tmp/LLM/Drivers/* except ./OpenAI and ./Gemini
mv "tmp/polyglot-tmp/LLM/Drivers/OpenAI" "tmp/tmp1/OpenAI"
mv "tmp/polyglot-tmp/LLM/Drivers/Gemini" "tmp/tmp1/Gemini"
rm -rf tmp/polyglot-tmp/LLM/Drivers/*
mv "tmp/tmp1/"* "tmp/polyglot-tmp/LLM/Drivers"
rm -rf tmp/tmp1
code2prompt "tmp/polyglot-tmp" -o "tmp/poly-cut.md"
# remove tmp/polyglot-tmp
rm -rf tmp/polyglot-tmp

# MAKE INSTRUCTOR WITH LIMITED NUMBER OF DRIVERS
cp -r "packages/instructor/src/"* "tmp/instructor-tmp/"
# remove everything under tmp/instructor-tmp/LLM/Drivers/* except ./OpenAI and ./Gemini
rm -rf "tmp/instructor-tmp/Extras"
code2prompt "tmp/instructor-tmp" -o "tmp/instructor-cut.md"
rm -rf tmp/instructor-tmp
