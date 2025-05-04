# Copy resource files to subpackages

echo "Copying resource files to:"

echo " ... ./packages/templates"
# Copy resources
mkdir -p ./packages/templates/config
cp -R ./config/* ./packages/templates/config
mkdir -p ./packages/templates/prompts
cp -R ./prompts/* ./packages/templates/prompts

echo " ... ./packages/setup"
# Copy resources
cp ./.env-dist ./packages/setup/.env-dist
mkdir -p ./packages/setup/prompts
cp -R ./prompts/* ./packages/setup/prompts
mkdir -p ./packages/setup/config
cp -R ./config/* ./packages/setup/config
mkdir -p ./packages/setup/bin
# copy setup script
cp ./bin/ins-setup ./packages/setup/bin
cp ./bin/bootstrap.php ./packages/setup/bin

echo " ... ./packages/http-client"
# Copy resources
mkdir -p ./packages/http-client/config
cp -R ./config/* ./packages/http-client/config

echo " ... ./packages/polyglot"
# Copy resources
cp ./.env-dist ./packages/polyglot/.env-dist
mkdir -p ./packages/polyglot/prompts
cp -R ./prompts/* ./packages/polyglot/prompts
mkdir -p ./packages/polyglot/config
cp -R ./config/* ./packages/polyglot/config

echo " ... ./packages/instructor"
# Copy resources
cp ./.env-dist ./packages/instructor/.env-dist
mkdir -p ./packages/instructor/prompts
cp -R ./prompts/* ./packages/instructor/prompts
mkdir -p ./packages/instructor/config
cp -R ./config/* ./packages/instructor/config

echo " ... ./packages/tell"
# Copy resources
cp ./.env-dist ./packages/tell/.env-dist
mkdir -p ./packages/tell/config
cp -R ./config/* ./packages/tell/config
mkdir -p ./packages/tell/prompts
cp -R ./prompts/* ./packages/tell/prompts
# Copy tell script
cp ./bin/ins-tell ./packages/tell/bin
cp ./bin/bootstrap.php ./packages/tell/bin

echo " ... ./packages/hub"
# Copy resources
cp ./.env-dist ./packages/hub/.env-dist
mkdir -p ./packages/hub/config
cp -R ./config/* ./packages/hub/config
mkdir -p ./packages/hub/prompts
cp -R ./prompts/* ./packages/hub/prompts
mkdir -p ./packages/hub/examples
cp -R ./examples/* ./packages/hub/examples
# Copy hub script
cp ./bin/ins-hub ./packages/hub/bin
cp ./bin/bootstrap.php ./packages/hub/bin

echo "Done!"
