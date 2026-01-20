# Better Examples - Assessment

## Goals
- Move examples out of `./examples` into `./packages/*/examples/`
- Make `composer hub` read examples from packages via a dedicated config file
- Make `composer docs` read examples from packages via the same config file
- Split Mintlify/MkDocs example indexes by package
- Keep changes small and localized to `packages/hub` and `packages/doctor`

## Current State (Relevant)
- `packages/hub/src/Services/ExampleRepository.php` scans a single base dir (`BasePath::get('examples')`)
- `packages/doctor/src/Docs.php` wires `ExampleRepository` with the same single base dir
- Example-to-tab/group mapping is hardcoded in `packages/hub/src/Data/Example.php` (A/B/C groups)
- Docs navigation is derived from `ExampleGroup` titles (flat grouping, no package nesting)
- `config/docs.yaml` has `examples.source`, but it is not used by the doc generators
- `packages/hub/examples` is a duplicate copy of `./examples`

## Constraints and Implications
- Most examples call `require 'examples/boot.php'` and expect the project root as CWD
- Moving examples without adjusting bootstrap paths will break `composer hub run`
- Example execution status uses canonical indices derived from example ordering
- Documentation output already uses `Example->toDocPath()` (`/cookbook/<tab>/<group>/<doc>`), so path changes are sensitive

## Assumptions to Confirm
- Target package mapping:
  - `A01-A05` -> `packages/instructor/examples`
  - `B01-B05` -> `packages/polyglot/examples`
  - `C01-C07` -> `packages/instructor/examples` (prompting is a subdomain, not a code package)
- It is acceptable to keep a minimal `./examples` folder with only `boot.php` for compatibility

## Risks
- Status history in `.hub/status.json` may become invalid if canonical ordering changes
- Doc navigation could shift if package grouping is introduced without stable ordering
- Any tooling that hardcodes `./examples` will need a targeted update
