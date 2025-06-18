#!/bin/bash
# Remove resource files from subpackages

TARGET_DIR="."

echo "Removing resource files from:"

###########################
### TEMPLATES RESOURCES ###
###########################
echo " ... ./packages/templates"
# config
rm -rf "$TARGET_DIR/packages/templates/config"
# prompts
rm -rf "$TARGET_DIR/packages/templates/prompts"
# release notes
rm -rf "$TARGET_DIR/packages/templates/release-notes/"*

#######################
### SETUP RESOURCES ###
#######################
echo " ... ./packages/setup"
# config
rm -rf "$TARGET_DIR/packages/setup/config/"*
# .env-dist
rm "$TARGET_DIR/packages/setup/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/setup/prompts/"*
# scripts
rm -rf "$TARGET_DIR/packages/setup/bin/"*
# release notes
rm -rf "$TARGET_DIR/packages/setup/release-notes/"*

#############################
### HTTP CLIENT RESOURCES ###
#############################
echo " ... ./packages/http-client"
# config
rm -rf "$TARGET_DIR/packages/http-client/config/"*
# release notes
rm -rf "$TARGET_DIR/packages/http-client/release-notes/"*

##########################
### POLYGLOT RESOURCES ###
##########################
echo " ... ./packages/polyglot"
# config
rm -rf "$TARGET_DIR/packages/polyglot/config/"*
# copy .env-dist
rm "$TARGET_DIR/packages/polyglot/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/polyglot/prompts"
# release notes
rm -rf "$TARGET_DIR/packages/polyglot/release-notes/"*

############################
### INSTRUCTOR RESOURCES ###
############################
echo " ... ./packages/instructor"
# config
rm -rf "$TARGET_DIR/packages/instructor/config/"*
# copy .env-dist
rm "$TARGET_DIR/packages/instructor/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/instructor/prompts/"*
# release notes
rm -rf "$TARGET_DIR/packages/instructor/release-notes/"*

######################
### TELL RESOURCES ###
######################
echo " ... ./packages/tell"
# config
rm -rf "$TARGET_DIR/packages/tell/config/"*
# copy .env-dist
rm "$TARGET_DIR/packages/tell/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/tell/prompts/"*
# Copy tell script
rm -rf "$TARGET_DIR/packages/tell/bin/"*
# release notes
rm -rf "$TARGET_DIR/packages/tell/release-notes/"*

#####################
### HUB RESOURCES ###
#####################
echo " ... ./packages/hub"
# config
rm -rf "$TARGET_DIR/packages/hub/config/"*
# examples
rm -rf "$TARGET_DIR/packages/hub/examples/"*
# copy .env-dist
rm "$TARGET_DIR/packages/hub/.env-dist"
# prompts
rm -rf "$TARGET_DIR/packages/hub/prompts/"*
# Copy hub script
rm -rf "$TARGET_DIR/packages/hub/bin/"*
# release notes
rm -rf "$TARGET_DIR/packages/hub/release-notes/"*

echo "Done!"
