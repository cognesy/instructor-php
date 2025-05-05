#!/bin/bash

# Copy resource files to subpackages

SOURCE_DIR="."
TARGET_DIR="."

echo "Copying resource files to:"

echo " ... ./packages/templates"
# Copy resources
mkdir -p "$TARGET_DIR/packages/templates/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/templates/config"
mkdir -p "$TARGET_DIR/packages/templates/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/templates/prompts"

echo " ... ./packages/setup"
# Copy resources
mkdir -p "$TARGET_DIR/packages/setup/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/setup/prompts"
mkdir -p "$TARGET_DIR/packages/setup/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/setup/config"
mkdir -p "$TARGET_DIR/packages/setup/bin"
# copy .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/setup/.env-dist"
# copy setup script
mkdir -p "$TARGET_DIR/packages/setup/bin"
cp "$SOURCE_DIR/bin/ins-setup" "$TARGET_DIR/packages/setup/bin/"
cp "$SOURCE_DIR/bin/bootstrap.php" "$TARGET_DIR/packages/setup/bin/"

echo " ... ./packages/http-client"
# Copy resources
mkdir -p "$TARGET_DIR/packages/http-client/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/http-client/config"

echo " ... ./packages/polyglot"
# Copy resources
mkdir -p "$TARGET_DIR/packages/polyglot/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/polyglot/prompts"
mkdir -p "$TARGET_DIR/packages/polyglot/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/polyglot/config"
# copy .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/polyglot/.env-dist"

echo " ... ./packages/instructor"
# Copy resources
mkdir -p "$TARGET_DIR/packages/instructor/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/instructor/prompts"
mkdir -p "$TARGET_DIR/packages/instructor/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/instructor/config"
# copy .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/instructor/.env-dist"

echo " ... ./packages/tell"
# Copy resources
mkdir -p "$TARGET_DIR/packages/tell/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/tell/config"
mkdir -p "$TARGET_DIR/packages/tell/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/tell/prompts"
# copy .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/tell/.env-dist"
# Copy tell script
mkdir -p "$TARGET_DIR/packages/tell/bin"
cp "$SOURCE_DIR/bin/tell" "$TARGET_DIR/packages/tell/bin/"
cp "$SOURCE_DIR/bin/bootstrap.php" "$TARGET_DIR/packages/tell/bin/"

echo " ... ./packages/hub"
# Copy resources
mkdir -p "$TARGET_DIR/packages/hub/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/hub/config"
mkdir -p "$TARGET_DIR/packages/hub/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/hub/prompts"
mkdir -p "$TARGET_DIR/packages/hub/examples"
cp -R "$SOURCE_DIR/examples/"* "$TARGET_DIR/packages/hub/examples"
# copy .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/hub/.env-dist"
# Copy hub script
mkdir -p "$TARGET_DIR/packages/hub/bin"
cp "$SOURCE_DIR/bin/ins-hub" "$TARGET_DIR/packages/hub/bin/"
cp "$SOURCE_DIR/bin/bootstrap.php" "$TARGET_DIR/packages/hub/bin/"

echo "Done!"
