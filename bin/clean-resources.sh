#!/bin/bash
# Remove resource files from subpackages

SOURCE_DIR="."
TARGET_DIR="./tmp/test2"

echo "Removing resource files from:"

###########################
### TEMPLATES RESOURCES ###
###########################
echo " ... ./packages/templates"
# config
rm -rf "$TARGET_DIR/packages/templates/config"
# prompts
rm -rf "$TARGET_DIR/packages/templates/prompts"

#######################
### SETUP RESOURCES ###
#######################
echo " ... ./packages/setup"
# config
rm -rf "$TARGET_DIR/packages/setup/config"
# .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/setup/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/setup/prompts"
# scripts
rm -rf "$TARGET_DIR/packages/setup/bin"

#############################
### HTTP CLIENT RESOURCES ###
#############################
echo " ... ./packages/http-client"
# config
rm -rf "$TARGET_DIR/packages/http-client/config"
mkdir -p "$TARGET_DIR/packages/http-client/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/http-client/config"

##########################
### POLYGLOT RESOURCES ###
##########################
echo " ... ./packages/polyglot"
# config
rm -rf "$TARGET_DIR/packages/polyglot/config"
mkdir -p "$TARGET_DIR/packages/polyglot/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/polyglot/config"
# copy .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/polyglot/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/polyglot/prompts"
mkdir -p "$TARGET_DIR/packages/polyglot/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/polyglot/prompts"

############################
### INSTRUCTOR RESOURCES ###
############################
echo " ... ./packages/instructor"
# config
rm -rf "$TARGET_DIR/packages/instructor/config"
mkdir -p "$TARGET_DIR/packages/instructor/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/instructor/config"
# copy .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/instructor/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/instructor/prompts"
mkdir -p "$TARGET_DIR/packages/instructor/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/instructor/prompts"

######################
### TELL RESOURCES ###
######################
echo " ... ./packages/tell"
# config
rm -rf "$TARGET_DIR/packages/tell/config"
mkdir -p "$TARGET_DIR/packages/tell/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/tell/config"
# copy .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/tell/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/tell/prompts"
mkdir -p "$TARGET_DIR/packages/tell/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/tell/prompts"
# Copy tell script
rm -rf "$TARGET_DIR/packages/tell/bin"
mkdir -p "$TARGET_DIR/packages/tell/bin"
cp "$SOURCE_DIR/bin/tell" "$TARGET_DIR/packages/tell/bin/"
#cp "$SOURCE_DIR/bin/bootstrap.php" "$TARGET_DIR/packages/tell/bin/"

#####################
### HUB RESOURCES ###
#####################
echo " ... ./packages/hub"
# config
rm -rf "$TARGET_DIR/packages/hub/config"
mkdir -p "$TARGET_DIR/packages/hub/config"
cp -R "$SOURCE_DIR/config/"* "$TARGET_DIR/packages/hub/config"
# examples
rm -rf "$TARGET_DIR/packages/hub/examples"
mkdir -p "$TARGET_DIR/packages/hub/examples"
cp -R "$SOURCE_DIR/examples/"* "$TARGET_DIR/packages/hub/examples"
# copy .env-dist
cp "$SOURCE_DIR/.env-dist" "$TARGET_DIR/packages/hub/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/hub/prompts"
mkdir -p "$TARGET_DIR/packages/hub/prompts"
cp -R "$SOURCE_DIR/prompts/"* "$TARGET_DIR/packages/hub/prompts"
# Copy hub script
rm -rf "$TARGET_DIR/packages/hub/bin"
mkdir -p "$TARGET_DIR/packages/hub/bin"
cp "$SOURCE_DIR/bin/instructor-hub" "$TARGET_DIR/packages/hub/bin/"
#cp "$SOURCE_DIR/bin/bootstrap.php" "$TARGET_DIR/packages/hub/bin/"

echo "Done!"
