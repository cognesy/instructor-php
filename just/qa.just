# Static analysis, style, structural rules, and local CI reproduction.

# Full QA suite: PHPStan + Psalm + Pint check + Semgrep.
[group('qa'), doc('Full QA: PHPStan + Psalm + Pint + Semgrep')]
qa:
    composer qa

# PHPStan static analysis.
[group('qa'), doc('PHPStan static analysis')]
qa-phpstan:
    composer qa:phpstan

# Psalm static analysis.
[group('qa'), doc('Psalm static analysis')]
qa-psalm:
    composer qa:psalm

# Pint code-style check (non-destructive).
[group('qa'), doc('Pint code-style check (no writes)')]
qa-pint:
    composer qa:pint

# Pint apply formatting (writes changes).
[group('qa'), doc('Pint apply formatting (writes)')]
qa-pint-fix:
    composer pint

# Semgrep structural / architectural rules.
[group('qa'), doc('Semgrep structural/architectural rules')]
qa-semgrep:
    composer qa:semgrep

# Dead-code detection via PHPStan.
[group('qa'), doc('Dead-code detection (PHPStan)')]
qa-dead-code:
    composer dead-code

# Unused composer dependency detection.
[group('qa'), doc('Unused composer dependency detection')]
qa-unused-deps:
    composer unused-deps

# Psalm unused-code pass.
[group('qa'), doc('Psalm unused-code pass')]
qa-psalm-unused:
    composer psalm-unused

# Psalm across every package (per-package pass).
[group('qa'), doc('Psalm across every package')]
qa-psalm-all:
    bash ./scripts/qa/psalm-all.sh

# PHPBench benchmarks.
[group('qa'), doc('PHPBench benchmarks')]
bench:
    composer bench

# Reproduce the GitHub Actions test workflow locally (needs act + docker/colima).
[group('ci'), doc('Run GitHub Actions test workflow locally via act')]
ci:
    act pull_request -W .github/workflows/php.yml -j build

# List workflows/jobs act can run.
[group('ci'), doc('List act-runnable workflows')]
ci-list:
    act --list

# Validate workflow resolution without running containers.
[group('ci'), doc('Dry-run the CI workflow (no containers)')]
ci-dryrun:
    act pull_request -W .github/workflows/php.yml -j build --dryrun
