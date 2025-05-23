#!/bin/bash

# Remove resource files from the project, except from their source directories.

TARGET_DIR="."

echo "Removing resource files from:"

echo " ... ./packages/templates"
rm -rf "$TARGET_DIR/packages/templates/config/"*
rm -rf "$TARGET_DIR/packages/templates/prompts/"*

echo " ... ./packages/setup"
rm -rf "$TARGET_DIR/packages/setup/prompts/"*
rm -rf "$TARGET_DIR/packages/setup/config/"*
rm -rf "$TARGET_DIR/packages/setup/bin/"*
rm -f "$TARGET_DIR/packages/setup/.env-dist"

echo " ... ./packages/http-client"
rm -rf "$TARGET_DIR/packages/http-client/config/"*

echo " ... ./packages/polyglot"
rm -rf "$TARGET_DIR/packages/polyglot/prompts/"*
rm -rf "$TARGET_DIR/packages/polyglot/config/"*
rm -f "$TARGET_DIR/packages/polyglot/.env-dist"

echo " ... ./packages/instructor"
rm -rf "$TARGET_DIR/packages/instructor/prompts/"*
rm -rf "$TARGET_DIR/packages/instructor/config/"*
rm -f "$TARGET_DIR/packages/instructor/.env-dist"

echo " ... ./packages/tell"
rm -rf "$TARGET_DIR/packages/tell/config/"*
rm -rf "$TARGET_DIR/packages/tell/prompts/"*
rm -rf "$TARGET_DIR/packages/tell/bin/"*
rm -f "$TARGET_DIR/packages/tell/.env-dist"

echo " ... ./packages/hub"
rm -rf "$TARGET_DIR/packages/hub/config/"*
rm -rf "$TARGET_DIR/packages/hub/prompts/"*
rm -rf "$TARGET_DIR/packages/hub/examples/"*
rm -rf "$TARGET_DIR/packages/hub/bin/"*
rm -f "$TARGET_DIR/packages/hub/.env-dist"

echo " ... ./docs-build"
rm -rf "$TARGET_DIR/docs-build/"*

echo "Done!"
