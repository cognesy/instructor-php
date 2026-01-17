# Full Modular Architecture Proposal

## Goal

Modularize documentation and examples so that:
1. Docs sources stored per package in `./packages/<package-name>/docs` (already done)
2. Examples modularized to `./packages/<package-name>/examples/`
3. Automatic consolidation using config and front matter
4. Support both Mintlify and MkDocs
5. `composer hub run` recognizes modular locations
6. Non-package-specific content supported

## Extended Front Matter Schema

```yaml
---
# REQUIRED
title: 'Working with Streaming Responses'
docname: 'streaming_responses'
package: 'polyglot'                    # NEW: Target package

# OPTIONAL
section: 'advanced'                    # NEW: Category within package
weight: 10                             # NEW: Sort order (lower = first)
tags: ['streaming', 'real-time']       # NEW: Cross-cutting discovery
difficulty: 'intermediate'             # NEW: beginner|intermediate|advanced
requires: ['polyglot']                 # NEW: Dependencies
requires_env: ['OPENAI_API_KEY']       # NEW: Required env vars
description: 'Short CLI description'   # NEW: For hub list
hidden: false                          # NEW: Hide from docs
---
```

## Proposed Directory Structure

```
/packages/
  instructor/
    docs/                              # Existing
    examples/                          # NEW
      basics/
        Basic/run.php
        Validation/run.php
      advanced/
        Streaming/run.php
      api-support/
        OpenAI/run.php

  polyglot/
    docs/
    examples/
      llm-basics/
        Inference/run.php
      llm-advanced/
        ContextCaching/run.php

/docs/                                 # Project-level (non-package)
  cookbook/
    prompting/                         # Prompting examples here
      zero-shot/
        EmotionPrompting/run.php

/examples/                             # DEPRECATED during migration
  boot.php                             # Keep shared bootstrap
```

## Configuration File: `/config/docs.yaml`

```yaml
docs:
  packages:
    instructor:
      tab: 'Instructor'
      menu_prefix: 'instructor'
      sections:
        basics:
          title: 'Cookbook \ Instructor \ Basics'
          weight: 1
        advanced:
          title: 'Cookbook \ Instructor \ Advanced'
          weight: 2
        troubleshooting:
          title: 'Cookbook \ Instructor \ Troubleshooting'
          weight: 3
        api-support:
          title: 'Cookbook \ Instructor \ API Support'
          weight: 4
        extras:
          title: 'Cookbook \ Instructor \ Extras'
          weight: 5

    polyglot:
      tab: 'Polyglot'
      menu_prefix: 'polyglot'
      sections:
        llm-basics:
          title: 'Cookbook \ Polyglot \ LLM Basics'
          weight: 1
        llm-advanced:
          title: 'Cookbook \ Polyglot \ LLM Advanced'
          weight: 2

  project:
    prompting:
      tab: 'Cookbook'
      source: '/docs/cookbook/prompting'
      sections:
        zero-shot:
          title: 'Zero-Shot Prompting'
        few-shot:
          title: 'Few-Shot Prompting'

  output:
    mintlify:
      target: 'docs-build'
      cookbook_path: 'cookbook'
    mkdocs:
      target: 'docs-mkdocs'
      cookbook_path: 'cookbook'

  discovery:
    example_filename: 'run.php'
    docs_extensions: ['.md', '.mdx']
```

## New Classes

### ModularExampleRepository

```php
class ModularExampleRepository {
    private array $sources = [];

    public function __construct(
        private string $configPath,
        private ?string $legacyExamplesDir = null
    ) {}

    public function discoverExamples(): array {
        $examples = [];

        // 1. Package examples
        foreach ($this->getPackageConfigs() as $package => $config) {
            $examples = array_merge(
                $examples,
                $this->discoverPackageExamples($package, $config)
            );
        }

        // 2. Project-level examples
        $examples = array_merge(
            $examples,
            $this->discoverProjectExamples()
        );

        // 3. Legacy fallback (migration period)
        if ($this->legacyExamplesDir) {
            $examples = array_merge(
                $examples,
                $this->discoverLegacyExamples()
            );
        }

        return $this->sortExamples($examples);
    }
}
```

### DocumentationMenuBuilder

```php
class DocumentationMenuBuilder {
    public function buildMintlifyNavigation(): array {
        $navigation = [];

        foreach ($this->config['packages'] as $package => $packageConfig) {
            $sections = $this->buildPackageSections($package, $packageConfig);
            foreach ($sections as $section) {
                $navigation[] = $section->toNavigationGroup();
            }
        }

        return $navigation;
    }
}
```

## CLI Enhancements

### Namespaced References
```bash
composer hub run polyglot:inference           # Package:example
composer hub run instructor:basics/streaming  # Package:section/example
composer hub run 47                           # Global index (backward compatible)
```

### New Commands
```bash
composer hub list polyglot                    # Package-specific listing
composer hub find --tag=streaming             # Tag-based search
composer hub new polyglot advanced/my_feature # Scaffold new example
composer hub validate                         # Validate front matter
```

## Migration Strategy

### Phase 1: Infrastructure (Non-Breaking)
1. Create `/config/docs.yaml`
2. Implement `ModularExampleRepository` with legacy fallback
3. Add new front matter fields (additive only)
4. Update generators to use config

### Phase 2: Package Migration (Gradual)
1. Start with `instructor` package
2. Copy examples from `/examples/A01_*/` to `/packages/instructor/examples/basics/`
3. Update front matter with `package: instructor`, `section: basics`
4. Test with both locations enabled

### Phase 3: Symlink Compatibility
```bash
ln -s ../packages/instructor/examples/basics/Basic /examples/A01_Basics/Basic
```

### Phase 4: Cleanup
1. Remove `/examples/` directory
2. Remove legacy fallback from repository
3. Update documentation

## Diagram

```
                    ┌─────────────────────────────────────┐
                    │        /config/docs.yaml            │
                    │    (package/section mapping)        │
                    └──────────────┬──────────────────────┘
                                   │
        ┌──────────────────────────┼──────────────────────────┐
        │                          │                          │
┌───────▼───────────┐    ┌─────────▼─────────┐    ┌──────────▼──────────┐
│ /packages/*/      │    │ /packages/*/docs/ │    │ /docs/ (project)    │
│ examples/         │    │ (package docs)    │    │ (shared content)    │
└───────┬───────────┘    └─────────┬─────────┘    └──────────┬──────────┘
        │                          │                          │
        └──────────────────────────┼──────────────────────────┘
                                   │
                    ┌──────────────▼──────────────┐
                    │  ModularExampleRepository   │
                    │  DocumentationMenuBuilder   │
                    └──────────────┬──────────────┘
                                   │
                    ┌──────────────┼──────────────┐
                    │              │              │
          ┌─────────▼─────────┐    │    ┌─────────▼─────────┐
          │ MintlifyDocs      │    │    │ MkDocsDocs        │
          │ → /docs-build/    │    │    │ → /docs-mkdocs/   │
          └───────────────────┘    │    └───────────────────┘
                            ┌──────▼──────┐
                            │ mint.json   │
                            │ mkdocs.yml  │
                            └─────────────┘
```

## Effort Estimate

- Infrastructure: ~20 hours
- Migration of 222 examples: ~40 hours
- Testing and validation: ~10 hours
- Documentation: ~5 hours
- **Total: ~75 hours**
