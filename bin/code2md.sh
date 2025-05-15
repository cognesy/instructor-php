#!/usr/bin/env bash
set -e  # stops script on first error

for dir in packages/*; do
  dirname=$(basename "$dir")
  if [ -d "$dir/src" ]; then
    echo "🔍 Exporting sources to Markdown in $dirname"
    code2prompt "$dir/src" -o "tmp/$dirname.md"
  fi
done

code2prompt "packages/utils/src/JsonSchema" -o "tmp/json-schema.md"
code2prompt "packages/utils/src/Messages" -o "tmp/messages.md"
code2prompt "packages/polyglot/src/LLM" -o "tmp/llm.md"
code2prompt "packages/polyglot/src/Embeddings" -o "tmp/embeddings.md"
