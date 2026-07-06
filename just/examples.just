# Deterministic examples via HTTP record/replay.
#
# Record real provider responses once (live, needs API keys), then replay them
# offline — fast, free, deterministic, keyless. See QUALITY.md for details.
# Recordings are namespaced per example and secrets are redacted on save.

# Where recordings are stored (per-example subdirs are created underneath).
RECORDINGS_DIR := "examples-recordings"

# Record ONE example's HTTP interactions live. e.g. `just examples-record getters_and_setters`
[group('examples'), doc('Record one example live (needs API keys) — e.g. just examples-record getters_and_setters')]
examples-record example *args:
    INSTRUCTOR_EXAMPLES_HTTP=record INSTRUCTOR_EXAMPLES_RECORDINGS_DIR={{RECORDINGS_DIR}} php ./bin/instructor-hub run {{example}} {{args}}

# Replay ONE example from recordings (offline, keyless). e.g. `just examples-replay getters_and_setters`
[group('examples'), doc('Replay one example offline — e.g. just examples-replay getters_and_setters')]
examples-replay example *args:
    INSTRUCTOR_EXAMPLES_HTTP=replay INSTRUCTOR_EXAMPLES_RECORDINGS_DIR={{RECORDINGS_DIR}} php ./bin/instructor-hub run {{example}} {{args}}

# Record the WHOLE corpus live (heavy — needs a key for every provider used).
# Pass extra flags through, e.g. `just examples-record-all --limit=20`.
[group('examples'), doc('Record all examples live (heavy, needs API keys) — accepts --limit/--filter')]
examples-record-all *args:
    INSTRUCTOR_EXAMPLES_HTTP=record INSTRUCTOR_EXAMPLES_RECORDINGS_DIR={{RECORDINGS_DIR}} php ./bin/instructor-hub all {{args}}

# Replay the WHOLE corpus offline — the deterministic examples gate (keyless, hermetic).
# `no-replay`-tagged examples are skipped automatically. e.g. `just examples-replay-all --limit=20`.
[group('examples'), doc('Replay all examples offline — deterministic gate (keyless)')]
examples-replay-all *args:
    INSTRUCTOR_EXAMPLES_HTTP=replay INSTRUCTOR_EXAMPLES_RECORDINGS_DIR={{RECORDINGS_DIR}} php ./bin/instructor-hub all {{args}}
