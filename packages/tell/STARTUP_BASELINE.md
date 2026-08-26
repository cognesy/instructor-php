# Tell startup baseline

Tell's startup budgets protect one-shot CLI ergonomics while the runtime is
being decomposed. They measure cold process startup separately from semantic
discovery passes, because wall-clock results alone are machine-dependent.

Run the reproducible probe from the repository root:

```bash
php packages/tell/scripts/benchmark-startup.php --iterations=10 --enforce
```

The command emits `tell.startup-baseline.v1` JSON. Each cold command gets one
unmeasured warm-up process followed by the requested number of fresh PHP
processes. The median is the enforceable startup value; p95 is diagnostic.

## Budgets

| Operation | Cold median | Workspace | Definitions | Manifests |
| --- | ---: | ---: | ---: | ---: |
| `tell --version` | 250 ms | 0 | 0 | 0 |
| bare `tell` home | 500 ms | 1 | 1 | 0 |
| `tell agents` | not timed | 0 | 1 | 0 |
| automatic stateless turn | provider excluded | 2 | 2 | 1 |

The turn counts are a baseline, not a performance target. The duplicate
workspace and definition discovery passes are explicitly retained here so the
runtime-simplification phase can prove their removal without guessing at the
old behavior. A scan means one semantic discovery invocation; it is not the
number of directories or installed packages visited inside that invocation.

`StartupScanCounter` is opt-in, factory-local diagnostic instrumentation. It
does not use static state and is not part of Tell's supported SDK facade.

## PHP compatibility

`cognesy/instructor-tell` continues to require PHP `^8.3`. The main test matrix
and post-split package matrix exercise PHP 8.3, 8.4, and 8.5. Package-split
tests install each package under the selected runtime, so Composer remains the
authority for package-specific constraints.
