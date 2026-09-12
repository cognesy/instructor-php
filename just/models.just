# Exact-offering model catalog maintenance.

[group('models'), doc('Build the runtime model catalog from checked-in inputs')]
models-build:
    composer models:build

[group('models'), doc('Check that the runtime model catalog is current (offline)')]
models-check:
    composer models:check

[group('models'), doc('Validate source catalogs and generated exact records (offline)')]
models-validate:
    composer models:validate
