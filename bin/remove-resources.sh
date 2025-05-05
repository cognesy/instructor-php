#!/bin/bash

# Copy resource files to subpackages

SOURCE_DIR="."
TARGET_DIR="."

echo "Removing resource files from:"

echo " ... ./packages/templates"
# Copy resources
rm -rf "$TARGET_DIR/packages/templates/config"
rm -rf "$TARGET_DIR/packages/templates/prompts"

echo " ... ./packages/setup"
# Copy resources
rm -rf "$TARGET_DIR/packages/setup/prompts"
rm -rf "$TARGET_DIR/packages/setup/config"
rm -rf "$TARGET_DIR/packages/setup/bin"
rm -f "$TARGET_DIR/packages/setup/.env-dist"

echo " ... ./packages/http-client"
# Copy resources
rm -rf "$TARGET_DIR/packages/http-client/config"

echo " ... ./packages/polyglot"
# Copy resources
rm -rf "$TARGET_DIR/packages/polyglot/prompts"
rm -rf "$TARGET_DIR/packages/polyglot/config"
rm -f "$TARGET_DIR/packages/polyglot/.env-dist"

echo " ... ./packages/instructor"
# Copy resources
rm -rf "$TARGET_DIR/packages/instructor/prompts"
rm -rf "$TARGET_DIR/packages/instructor/config"
rm -f "$TARGET_DIR/packages/instructor/.env-dist"

echo " ... ./packages/tell"
# Copy resources
rm -rf "$TARGET_DIR/packages/tell/config"
rm -rf "$TARGET_DIR/packages/tell/prompts"
rm -rf "$TARGET_DIR/packages/tell/bin"
rm -f "$TARGET_DIR/packages/tell/.env-dist"

echo " ... ./packages/hub"
# Copy resources
rm -rf "$TARGET_DIR/packages/hub/config"
rm -rf "$TARGET_DIR/packages/hub/prompts"
rm -rf "$TARGET_DIR/packages/hub/examples"
rm -rf "$TARGET_DIR/packages/hub/bin"
rm -f "$TARGET_DIR/packages/hub/.env-dist"

echo "Done!"
