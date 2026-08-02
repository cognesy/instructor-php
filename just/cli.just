# Project CLI entrypoints (thin passthroughs to the bin/ tools).

# Instructor Hub CLI. e.g. `just hub list`
[group('cli'), doc('Instructor Hub CLI — e.g. just hub list')]
hub *args:
    composer hub {{args}}

# Tell CLI (interactive assistant / prompt runner).
[group('cli'), doc('Tell CLI')]
tell *args:
    composer tell {{args}}

# Setup wizard / publisher.
[group('cli'), doc('Instructor setup wizard')]
setup-cli *args:
    composer setup {{args}}

# Instructor docs CLI passthrough. e.g. `just docs list`
[group('cli'), doc('Docs CLI passthrough — e.g. just docs list')]
docs *args:
    composer docs {{args}}
