# Simplified Documentation Architecture

## Structure

```
┌─────────────────────────────────────────────────────────────────┐
│                        Documentation                             │
├─────────────────┬─────────────────┬────────────────┬────────────┤
│   Main Docs     │    Packages     │    Examples    │    More    │
│   (./docs)      │ (./packages/*   │  (./examples)  │  (future)  │
│                 │     /docs)      │                │            │
├─────────────────┼─────────────────┼────────────────┼────────────┤
│ - Overview      │ Package listing │ As-is          │            │
│ - Getting       │ with brief      │ (restructure   │            │
│   Started       │ descriptions    │  later)        │            │
│ - Release Notes │                 │                │            │
│ - etc.          │ Click package → │                │            │
│                 │ see its docs    │                │            │
│ Manually        │                 │                │            │
│ curated         │ Each package    │                │            │
│                 │ has own         │                │            │
│                 │ structure       │                │            │
└─────────────────┴─────────────────┴────────────────┴────────────┘
```

## Tab/Section Mapping

| Section | Source | Content |
|---------|--------|---------|
| **Main** | `./docs/` | Overview, getting started, concepts - manually curated |
| **Packages** | `./packages/*/docs/` | Auto-discovered package documentation |
| **Cookbook** | `./examples/` | Examples (as-is for now) |
| **Changelog** | `./docs/release-notes/` | Release notes |

## Packages Section Behavior

### Landing Page
A listing of all packages with:
- Package name
- Brief description (from config or composer.json)
- Link to package docs

### Package Docs
Each package has its own structure in `./packages/<package>/docs/`:
- Self-contained
- Own navigation structure
- Tightly coupled to code

### Autodiscovery
```
1. Scan ./packages/ for directories
2. Check if ./packages/<name>/docs/ exists
3. If yes, include in packages listing
4. Build navigation from package's docs structure
```

## Configuration

### Minimal Config: `/config/docs.yaml`

```yaml
# Main docs configuration
main:
  title: 'Instructor for PHP'
  source: './docs'

# Packages - autodiscovered from ./packages/*/docs/
packages:
  source_pattern: './packages/*/docs'
  # Optional: override descriptions (otherwise from composer.json)
  descriptions:
    instructor: 'Core structured output extraction library'
    polyglot: 'Unified LLM API abstraction layer'
    http-client: 'Framework-agnostic HTTP client'
    # ... add as needed

# Examples
examples:
  source: './examples'
  # Keep current structure for now

# Output targets (BOTH required)
output:
  mintlify:
    target: './docs-build'
    config: 'mint.json'
  mkdocs:
    target: './docs-mkdocs'
    config: 'mkdocs.yml'
```

## Mintlify Structure

```json
{
  "primaryTab": { "name": "Main" },
  "tabs": [
    { "name": "Packages", "url": "packages" },
    { "name": "Cookbook", "url": "cookbook" },
    { "name": "Changelog", "url": "release-notes" }
  ],
  "navigation": [
    // Main docs (manually curated)
    { "group": "Main", "pages": ["overview", "quickstart", "..."] },

    // Packages (auto-generated)
    { "group": "Packages", "pages": ["packages/index"] },
    { "group": "Packages \\ Instructor", "pages": ["packages/instructor/..."] },
    { "group": "Packages \\ Polyglot", "pages": ["packages/polyglot/..."] },
    // ... auto-generated for each package with docs

    // Cookbook (existing)
    { "group": "Cookbook", "pages": ["cookbook/..."] },

    // Changelog (existing)
    { "group": "Release Notes", "pages": ["release-notes/..."] }
  ]
}
```

## MkDocs Structure

```yaml
nav:
  - Main:
    - Overview: index.md
    - Quickstart: quickstart.md
    # ... manually curated

  - Packages:
    - Index: packages/index.md
    - Instructor:
      - Overview: packages/instructor/index.md
      - Essentials: packages/instructor/essentials.md
      # ... auto-generated from package docs structure
    - Polyglot:
      - Overview: packages/polyglot/index.md
      # ...
    # ... auto-generated for each package

  - Cookbook:
    # ... existing structure

  - Changelog:
    # ... existing structure
```

## Key Simplifications

| Before | After |
|--------|-------|
| 5-6 category tabs | 4 tabs: Main, Packages, Cookbook, Changelog |
| Complex category grouping | Flat package listing |
| Front matter with package/section/weight | Simple discovery by directory existence |
| ~88 files need front matter updates | No front matter changes required |

## Implementation

### Generator Flow

```
1. Load config from /config/docs.yaml
2. Process Main docs:
   - Copy ./docs/ to output (excluding packages content)
   - Respect existing structure

3. Discover packages:
   - Scan ./packages/*/docs/
   - For each existing docs dir:
     - Get description from config or composer.json
     - Copy docs to output/packages/<name>/
     - Build navigation from directory structure
   - Generate packages/index page (listing)

4. Process Examples:
   - Use existing cookbook generation

5. Process Changelog:
   - Use existing release notes generation

6. Generate navigation files:
   - mint.json for Mintlify
   - mkdocs.yml for MkDocs
```

### Files to Modify

| File | Change |
|------|--------|
| `MintlifyDocumentation.php` | Use config, add package autodiscovery |
| `MkDocsDocumentation.php` | Use config, add package autodiscovery |
| `Docs.php` | Load config, wire services |

### New Files

| File | Purpose |
|------|---------|
| `/config/docs.yaml` | Central configuration |
| `PackageDiscovery.php` | Scan packages for docs |
| `PackageDocsBuilder.php` | Build package navigation |

## Adding a New Package's Docs

**Step 1**: Create docs directory
```bash
mkdir -p packages/my-package/docs
```

**Step 2**: Add docs files
```markdown
<!-- packages/my-package/docs/index.md -->
# My Package

Overview of my package...
```

**Step 3**: (Optional) Add description to config
```yaml
# /config/docs.yaml
packages:
  descriptions:
    my-package: 'Description of my package'
```

**Step 4**: Regenerate docs
```bash
composer docs gen:mintlify
composer docs gen:mkdocs
```

Package automatically appears in the Packages listing!

## Parallel Output (Mintlify + MkDocs)

Both outputs generated from same sources:

```
./docs/                          →  docs-build/        (Mintlify)
./packages/*/docs/                   docs-mkdocs/       (MkDocs)
./examples/

Same content, different formats:
- Mintlify: .mdx files, mint.json navigation
- MkDocs: .md files, mkdocs.yml navigation
```

### Format Differences Handled

| Aspect | Mintlify | MkDocs |
|--------|----------|--------|
| Extension | `.mdx` | `.md` |
| Navigation | `mint.json` (JSON) | `mkdocs.yml` (YAML) |
| Groups | `"group": "Name"` | Nested YAML |
| Tabs | `"tabs": [...]` | `navigation.tabs` |
