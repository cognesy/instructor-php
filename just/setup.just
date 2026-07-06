# Environment bootstrap.

# Install root composer dependencies.
[group('setup'), doc('Install root composer dependencies')]
install:
    composer install

# Full bootstrap: root deps + every package's deps.
[group('setup'), doc('Bootstrap: root deps + all package deps')]
bootstrap:
    composer install
    bash ./scripts/packages/composer-install-all.sh

# Dump / optimize the root autoloader.
[group('setup'), doc('Dump/optimize root autoloader')]
autoload:
    composer dump-autoload
