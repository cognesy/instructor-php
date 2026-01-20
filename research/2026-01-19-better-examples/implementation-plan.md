# Better Examples - Implementation Plan

## Summary
Introduce a dedicated examples config file and extend `ExampleRepository` to read multiple package sources. Update `Hub` and `Doctor` to use this config. Adjust doc navigation to group examples by package, and relocate example files into package-specific directories with minimal bootstrap changes.

## Proposed Config (Dedicated)
Add `config/examples.yaml` (new file):

```yaml
version: 1
sources:
  - package: instructor
    path: ./packages/instructor/examples
  - package: polyglot
    path: ./packages/polyglot/examples
```

Notes:
- Order in the list defines global example ordering.
- If missing, fall back to `./examples` for backward compatibility.

## Phase 1 - Infrastructure (Hub + Doctor)

1. Add `ExampleSourcesConfig` in `packages/hub`:
   - Reads `config/examples.yaml` via `BasePath::get()`
   - Normalizes paths to absolute roots
   - Provides ordered list of sources with `package` and `path`

2. Extend `ExampleRepository` to support multiple sources:
   - Accept `ExampleSourcesConfig` or an array of sources
   - Iterate sources in order, scan subdirectories per source
   - Build examples with `Example::fromFile($baseDir, $path)`
   - Add a stable source id to `Example->relativePath` (ex: `instructor:A01_Basics/Basic`)
   - Preserve current group mapping in `Example.php`

3. Wire the new config in `Hub`:
   - In `packages/hub/src/Hub.php`, build `ExampleRepository` via the config
   - Keep a fallback to the legacy `./examples` if config is missing

4. Wire the new config in `Doctor`:
   - In `packages/doctor/src/Docs.php`, construct `ExampleRepository` from the same config
   - Avoid touching `config/docs.yaml` for source paths (still used for intro pages)

## Phase 2 - Navigation and Indexes (Doctor)

1. Group examples by package for MkDocs:
   - Update `NavigationBuilder::buildCookbookNav()` to nest by package:
     - `Cookbook -> Instructor -> [Basics, Advanced, Prompting, ...]`
     - `Cookbook -> Polyglot -> [LLM Basics, LLM Advanced, ...]`

2. Group examples by package for Mintlify:
   - Update `MintlifyDocumentation::updateHubIndex()` to add package-level groups
   - Use nested `NavigationGroup` entries for each package

3. Create package-level example index pages:
   - Generate `docs-build/cookbook/<package>/index.mdx`
   - Generate `docs-mkdocs/cookbook/<package>/index.md`
   - Update nav to include these indexes as entry points

## Phase 3 - Content Move (Examples)

1. Move example directories:
   - `examples/A01_*` -> `packages/instructor/examples/A01_*`
   - `examples/A02_*` -> `packages/instructor/examples/A02_*`
   - `examples/A03_*` -> `packages/instructor/examples/A03_*`
   - `examples/A04_*` -> `packages/instructor/examples/A04_*`
   - `examples/A05_*` -> `packages/instructor/examples/A05_*`
   - `examples/B01_*` -> `packages/polyglot/examples/B01_*`
   - `examples/B02_*` -> `packages/polyglot/examples/B02_*`
   - `examples/B03_*` -> `packages/polyglot/examples/B03_*`
   - `examples/B04_*` -> `packages/polyglot/examples/B04_*`
   - `examples/B05_*` -> `packages/polyglot/examples/B05_*`
   - `examples/C01_*` -> `packages/instructor/examples/C01_*`
   - `examples/C02_*` -> `packages/instructor/examples/C02_*`
   - `examples/C03_*` -> `packages/instructor/examples/C03_*`
   - `examples/C04_*` -> `packages/instructor/examples/C04_*`
   - `examples/C05_*` -> `packages/instructor/examples/C05_*`
   - `examples/C06_*` -> `packages/instructor/examples/C06_*`
   - `examples/C07_*` -> `packages/instructor/examples/C07_*`

2. Bootstrap handling:
   - Keep `./examples/boot.php` as a shared bootstrap, or
   - Replace `require 'examples/boot.php'` with a project-root safe include
     (ex: `require __DIR__ . '/../../../../examples/boot.php'`)
   - Prefer keeping `boot.php` at root to avoid touching all example files

3. Remove duplication:
   - Delete `packages/hub/examples` after migration to avoid drift

## Phase 4 - Validation and Cleanup

1. Hub checks:
   - `composer hub list`
   - `composer hub run <example>`
   - Validate `.hub/status.json` (reset if ordering changed)

2. Docs checks:
   - `composer docs -- gen:mintlify --examples-only`
   - `composer docs -- gen:mkdocs --examples-only`
   - Confirm package-split navigation and index pages

3. Update references:
   - Search for hardcoded `./examples` paths and update if required
   - Review `composer.json` `autoload-dev` `Examples\\` mapping if it depends on root examples

## Open Questions
- Should prompting examples remain under `packages/instructor` or get a dedicated package?
- Is it acceptable to keep `./examples/boot.php` as a shared bootstrap?
- Do we want example status history to be preserved or reset after migration?
