# Exact-offering model catalog maintenance.

[group('models'), doc('Build the runtime model catalog from checked-in inputs')]
models-build:
    composer models:build

[group('models'), doc('Check that the runtime model catalog is current (offline)')]
models-check:
    composer models:check

[group('models'), doc('Validate catalogs and exact mappings (offline)')]
models-validate:
    composer models:validate

[group('models'), doc('List maintained offerings and provenance')]
models-list *args:
    composer models:list -- {{args}}

[group('models'), doc('Fetch a snapshot and stage only explicitly mapped changes')]
models-refresh *args:
    composer models:refresh -- {{args}}

[group('models'), doc('Apply a reviewed staged model refresh atomically')]
models-apply:
    composer models:apply
