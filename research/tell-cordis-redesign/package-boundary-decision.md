# Tell package-boundary decision

Date: 2026-08-27

## Decision

Keep the static composition host, capability contracts, standard modules, SDK,
CLI adapter, and bounded protocol in `cognesy/instructor-tell`. Do not create a
package per contract or module.

## Evidence

- Public definitions replace workspace, observer, model/driver, tool, and
  execution capabilities without private APIs or service-location.
- The shared conformance fixtures cover standard filesystem and independent
  in-memory workspace implementations. External observation and driver
  replacements boot through `TellHostBuilder::replace()`.
- Graph tests cover absent, duplicate, invalid, cyclic, partially constructed,
  and reverse-order disposal cases. Architecture tests keep contracts free of
  Symfony and concrete runtime dependencies.
- `packages/tell/scripts/clean-consumer.sh` creates an artifact archive and
  resolves it from clean Composer consumers for simulated PHP 8.3, 8.4, and
  8.5 platforms with both lowest and highest dependency selection. The smoke
  runs on the installed PHP binary. No path repository, monorepo autoload, or
  root development namespace is available.

## Rationale

The seams now justify source modules, not separately versioned packages. The
host, SDK, CLI, and protocol change together and share one small public contract
surface. There is no independent release cadence or consumer demand that would
offset the extra dependency graph, compatibility matrix, and discovery cost of
splitting them.

Revisit only after at least one module has an independent implementation and
release cadence used by more than one external consumer. Compatibility remains
Composer SemVer plus conformance; Tell does not negotiate runtime semantic
capability versions.
