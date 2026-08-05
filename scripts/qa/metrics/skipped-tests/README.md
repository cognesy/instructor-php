# Skipped-test measurement

This is a project-owned adaptation of the experimental
`xqa:pest/skipped-test-count` example. It has no runtime or upgrade relationship
with xqa.

## Operational definition

`run` measures skipped test cases observed by instructor-php's fast QA lane:

- Unit, Feature, and Regression suites under parallel Pest, excluding
  `docs-qa`;
- the Serial suite under compact, single-process Pest.

These commands mirror `scripts/test/fast.sh`, which remains the authoritative
definition of the fast lane. If that file changes, this measurement must be
reviewed and adapted before comparing new results with the previous series.

The wrapper does not invoke `fast.sh` because it forwards identical extra
arguments to both passes. Supplying one JUnit path would cause the second pass
to overwrite the first. For this spike, `run` executes the two evidenced lane
commands with distinct report paths.

## Operate

Run the complete measurement from any repository directory:

```shell
scripts/qa/metrics/skipped-tests/run
```

Native Pest output, JUnit reports, the unannotated measurement, and the final
result are retained under `builds/qa-metrics/`. Standard output contains only
the final JSON result. A concise execution summary is written to standard
error.

The wrapper returns success when the skipped-test measurement is valid, even if
Pest reports test failures. The JSON execution context preserves the exit code
of each native pass so the caller does not confuse a valid measurement with a
green QA gate.

To measure existing artifacts without executing Pest:

```shell
scripts/qa/metrics/skipped-tests/measure parallel.xml serial.xml
```

## Customize and maintain

The repository owns these files. Change their interface, language, paths, and
dependencies when local needs justify it. Keep these invariants:

1. Every distinct part of the authoritative lane writes a distinct artifact.
2. Standard output contains one bounded JSON result.
3. Failed or incomplete evidence never becomes a false zero.
4. Native execution status remains visible alongside the metric.
5. Changes to lane semantics trigger an explicit comparability review.

## Troubleshoot

- Inspect the retained `parallel.log` and `serial.log` when a native pass fails.
- Inspect the corresponding XML when a count is surprising.
- A missing XML normally means Pest terminated before producing a complete
  report.
- A zero result means no skipped test cases were present in the supplied
  reports. It does not prove that every intended test was selected.

The copied fixtures provide a cheap deterministic smoke test:

```shell
scripts/qa/metrics/skipped-tests/measure \
  scripts/qa/metrics/skipped-tests/fixtures/no-skips.xml \
  scripts/qa/metrics/skipped-tests/fixtures/one-skip.xml
```
