# Test lanes — fast by default via Pest parallel (paratest, 24 cores).
# The fast lane runs the SAME coverage as `composer test` but ~3x faster.
#
# `test` (full suite) is the authoritative, stably-green gate. The scoped
# commands `test-package` / `test-changed` are best-effort: a few packages
# (currently addons, telemetry) have pre-existing test-pollution / env-dependent
# tests that pass only within the full run, so they can report false failures in
# isolation. When a scoped run fails unexpectedly, confirm with `just test`.

# Fast lane: Unit+Feature+Regression, parallel + a tiny serial pass (~6s).
# Run on every change. Same coverage as `composer test`, ~3x faster.
[group('test'), doc('Fast lane: Unit+Feature+Regression, parallel (~6s)')]
test *filter:
    bash ./scripts/test/fast.sh {{filter}}

# Same coverage, serial + compact (readable failure output).
[group('test'), doc('Fast lane serial/compact — clearer failures')]
test-serial *filter:
    php -d memory_limit=512M ./vendor/bin/pest --compact --testsuite=Unit,Feature,Regression --exclude-group=docs-qa {{filter}}

# Unit lane only, parallel (~2s) — tightest inner loop.
[group('test'), doc('Unit lane only, parallel (~2s)')]
test-unit *filter:
    php -d memory_limit=1G ./vendor/bin/pest --parallel --testsuite=Unit {{filter}}

# Feature lane only.
[group('test'), doc('Feature lane only, parallel')]
test-feature *filter:
    php -d memory_limit=1G ./vendor/bin/pest --parallel --testsuite=Feature {{filter}}

# Regression lane only.
[group('test'), doc('Regression lane only, parallel')]
test-regression *filter:
    php -d memory_limit=1G ./vendor/bin/pest --parallel --testsuite=Regression {{filter}}

# Integration lane — slow, may need API keys. Excluded from the fast lane and CI.
[group('test'), doc('Integration lane — slow, may need API keys')]
test-integration *filter:
    php -d memory_limit=1G ./vendor/bin/pest --testsuite=Integration {{filter}}

# Fast lanes for a single package (Unit+Feature+Regression, no Integration),
# serial for reliability. e.g. `just test-package agents`
[group('test'), doc('Fast lanes for one package — e.g. just test-package agents')]
test-package pkg *filter:
    bash ./scripts/test/test-package.sh {{pkg}} {{filter}}

# Full package suite INCLUDING Integration (slow, may need API keys).
[group('test'), doc('One package incl. Integration (slow, may need keys)')]
test-package-full pkg *filter:
    php -d memory_limit=1G ./vendor/bin/pest --parallel packages/{{pkg}}/tests {{filter}}

# Fast lanes for packages changed vs a git ref (default: main), incl. uncommitted.
# Excludes Integration so it stays a fast, keyless pre-push gate.
[group('test'), doc('Fast lanes for packages changed vs a ref (default main)')]
test-changed base='main':
    bash ./scripts/test/test-changed.sh {{base}}

# Everything including the Integration lane, parallel.
[group('test'), doc('All lanes incl. Integration, parallel')]
test-all *filter:
    php -d memory_limit=1G ./vendor/bin/pest --parallel {{filter}}

# Docs-QA test group (excluded from the fast lane).
[group('test'), doc('Docs-QA test group')]
test-docs:
    php -d memory_limit=512M ./vendor/bin/pest --compact --group=docs-qa

# Opt-in telemetry live-backend interop suite (needs env vars — see QUALITY.md).
[group('test'), doc('Telemetry live-backend interop suite (opt-in)')]
test-telemetry:
    php -d memory_limit=512M ./vendor/bin/pest --compact packages/telemetry/tests/Integration

# Run each package's own composer test in isolation (slow, cross-package sanity).
[group('test'), doc('Each package composer test in isolation (slow)')]
test-isolated:
    bash ./scripts/test/run-all-tests.sh

# Coverage report for the fast lane (needs xdebug or pcov).
[group('test'), doc('Coverage for the fast lane (needs xdebug/pcov)')]
test-coverage:
    php -d memory_limit=1G ./vendor/bin/pest --parallel --testsuite=Unit,Feature,Regression --coverage
