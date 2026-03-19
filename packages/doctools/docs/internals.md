---
title: 'Internals'
description: 'Internal architecture of doctools: configuration, processing flow, outputs, and current limitations'
---

# Internals

This document explains how `packages/doctools` generates documentation today, where configuration lives, and which parts of the pipeline own discovery, copying, navigation, and final output.

Use it as the starting point when you need to:

- add a new documentation source
- change how Mintlify or MkDocs navigation is built
- debug missing pages or broken ordering
- understand why generated navigation looks the way it does

## Mental Model

The package builds multiple outputs from the same repository content:

- **Mintlify** output in `builds/docs-build`
- **MkDocs** output in `builds/docs-mkdocs`
- **LLM docs** output in `builds/build-llms`

The pipeline is intentionally simple:

1. Copy the manually curated root docs from `docs/`
2. Discover package docs from `packages/*/docs`
3. Discover cheatsheets from `packages/*/CHEATSHEET.md`
4. Discover cookbook examples through `ExampleRepository`
5. Copy and normalize files for the target format
6. Build generated index pages and navigation configs

The package does not keep a separate docs database. It infers structure from the repository layout and a small set of metadata files.

## Main Inputs

### Repository content

- `docs/` - manually curated top-level docs, release notes, Mintlify seed config, MkDocs template, packages landing page
- `packages/*/docs/` - per-package documentation
- `packages/*/CHEATSHEET.md` - per-package cheatsheets
- `examples/` - source material for cookbook pages, discovered through `ExampleRepository`

### Configuration

The main typed config object is `DocsConfig`, loaded from:

- `packages/doctools/resources/config/docs.yml`

This file controls:

- package descriptions
- package output directory remapping
- package display order
- internal packages excluded from public docs
- cheatsheet discovery
- cookbook intro pages
- output target paths
- llms.txt generation and deployment settings

### Templates and seed files

- `docs/mint.json` - Mintlify seed config; doctools overwrites its navigation
- `docs/mkdocs.yml.template` - MkDocs theme/plugin template; doctools overwrites its `nav`
- `docs/packages.md` - source for the generated packages landing page

## Core Components

### Application wiring

`Cognesy\Doctools\Docs` is the console application entry point.

It constructs:

- `ExampleRepository`
- `MintlifyDocumentation`
- `MkDocsDocumentation`
- the Symfony Console commands that expose generation

The application wires repository paths such as `docs/`, `builds/docs-build`, and `builds/docs-mkdocs`.

### Discovery

Discovery is handled by focused services:

- `PackageDiscovery` - scans `packages/*`, keeps packages with a `docs/` directory, filters internal packages, reads descriptions from config or `composer.json`, and maps package names to output directories
- `CheatsheetDiscovery` - scans `packages/*/CHEATSHEET.md`, reads frontmatter, filters internal packages, and sorts by configured package order

These services operate on repository state directly. There is no cached manifest.

### Copy/build phase

`PackageDocsBuilder` copies one package's docs to the target output tree and then:

- renames `.md` to `.mdx` for Mintlify
- rewrites links for the target format
- inlines external code blocks

This is the shared package-docs builder used by both Mintlify and MkDocs generation.

### Navigation and ordering

There are two distinct navigation systems:

- `MintlifyDocumentation::updateHubIndex()` builds Mintlify navigation and writes `mint.json`
- `NavigationBuilder` builds MkDocs `nav` arrays, which are then written into `mkdocs.yml`

Ordering is controlled by `DocsMetadata`, which reads:

1. `_meta.yaml` or `_meta.yml` in a docs directory
2. frontmatter `sidebarPosition` / `sidebar_position`
3. hardcoded default priorities for common names such as `index`, `introduction`, `overview`, `essentials`, `advanced`, and `internals`

This metadata controls **order**, not display labels.

## Output-Specific Flow

### Mintlify

Mintlify generation is handled by `MintlifyDocumentation`.

The flow is:

1. Remove `builds/docs-build`
2. Copy `docs/` into `builds/docs-build`
3. Rename copied `.md` files to `.mdx`
4. Rewrite links for Mintlify
5. Generate `packages/index.mdx`
6. Copy discovered package docs into `builds/docs-build/packages/...`
7. Copy cheatsheets into `builds/docs-build/cheatsheets/...`
8. Regenerate `mint.json` navigation
9. Copy cookbook examples into `builds/docs-build/cookbook/...`
10. Strip YAML frontmatter from generated `.mdx` files

Important details:

- Mintlify navigation is rebuilt from the current build output, not from an intermediate model
- `docs/mint.json` is treated as a seed file for site config, but its navigation is replaced
- package pages are added to navigation as page paths, not as explicit titled nav items

### MkDocs

MkDocs generation is handled by `MkDocsDocumentation`.

The flow is:

1. Remove `builds/docs-mkdocs`
2. Copy `docs/` into `builds/docs-mkdocs`
3. Rename copied `.mdx` files to `.md`
4. Remove Mintlify-only files from the copied tree
5. Copy discovered package docs into `builds/docs-mkdocs/packages/...`
6. Generate `packages/index.md`
7. Copy cheatsheets into `builds/docs-mkdocs/cheatsheets/...`
8. Copy cookbook examples into `builds/docs-mkdocs/cookbook/...`
9. Rewrite absolute `/images/...` links so they work from nested markdown files
10. Load `docs/mkdocs.yml.template`, remove its existing `nav`, and write a generated `mkdocs.yml`

MkDocs keeps more of its structure in the YAML config, while Mintlify pushes more responsibility into `mint.json`.

### LLM docs

LLM docs are generated from the MkDocs navigation tree, not independently from repository scanning.

This matters because:

- if MkDocs navigation is wrong, llms output will mirror that
- llms generation depends on the MkDocs output tree already existing

## Generated Index Pages

Two generated content index pages are produced for both outputs:

- `packages/index.md(x)`
- `cheatsheets/index.md(x)`

### Packages index

`PackagesIndexGenerator` uses:

- `docs/packages.md` if it exists
- otherwise a fallback generated listing

Current behavior is simple: when `docs/packages.md` exists, it is copied as-is. The generator does not currently append discovered package entries to that file.

### Cheatsheets index

`CheatsheetsIndexGenerator` always generates a simple list of discovered cheatsheets and their descriptions.

## How Navigation Labels Are Produced Today

This is the part that causes most of the "raw" left-nav output.

### What metadata is used

Current metadata is used for:

- ordering
- package descriptions
- package exclusion
- package output directory remapping
- cheatsheet title and description

Current metadata is **not** used consistently for:

- page labels in generated navigation
- friendly replacements for filename-based slugs
- normalizing acronyms such as `CLI`, `LLM`, or branded names such as `OpenCode`

### Mintlify limitation in the current implementation

The Mintlify generator currently writes page entries as plain page paths like:

```json
"packages/agents/01-introduction"
```

Because the current `NavigationItem` abstraction only supports:

- a page path string, or
- a nested group

there is no place to store an explicit display label for a page in generated `mint.json`.

That means Mintlify falls back to slug-like labels derived from filenames. The result is:

- numeric prefixes leaking into the nav (`01 introduction`)
- lowercase acronyms becoming words (`cli tools`)
- page labels ignoring frontmatter `title`

### MkDocs limitation in the current implementation

MkDocs labels are generated from filenames via `NavigationBuilder::formatTitle()`.

This is better than Mintlify because it strips numeric prefixes and handles some special cases, but it still does not read page titles from frontmatter. Friendly labels are inferred from filenames, not sourced from page metadata.

## What Controls the Rawness of the Nav

When the left nav looks raw, the root causes are usually one of these:

1. Files are named with numeric prefixes for ordering, but the nav builder is using filenames as labels instead of metadata titles
2. `_meta.yaml` is present for order, but there is no parallel metadata source for explicit labels
3. Mintlify navigation items do not support `{ title, page }`-style output in the current abstraction
4. Frontmatter `title` exists in many package docs, but the generators do not consume it for nav labels
5. Mintlify strips frontmatter from copied `.mdx` files after generation, so titles are not preserved in generated files as a fallback source

In short: the biggest missing piece is not more `_meta.yaml` ordering data. It is a **title-aware navigation model** for generated nav.

## Practical Debugging Guide

When generated docs look wrong, debug in this order:

1. Check `packages/doctools/resources/config/docs.yml`
2. Check whether the package is excluded as internal
3. Check whether the source package has `packages/<name>/docs/`
4. Check `_meta.yaml` in the relevant docs directory
5. Check frontmatter for `sidebarPosition`
6. Regenerate the output and inspect the built files in `builds/docs-build` or `builds/docs-mkdocs`
7. Inspect generated `builds/docs-build/mint.json` or repo-root `mkdocs.yml`

## Current Gaps and Improvement Targets

If you want to improve the developer and reader experience, the highest-value changes are:

1. Make both Mintlify and MkDocs nav generation consume frontmatter `title`
2. Extend the Mintlify navigation model so page items can carry explicit labels
3. Add optional nav metadata beyond ordering, for example `navTitle`
4. Keep the naming strategy consistent across formats so MkDocs, Mintlify, and llms output match
5. Add integration tests that assert generated nav labels, not only file copying

## Safe Extension Points

The lowest-risk places to extend behavior are:

- `DocsConfig` and `docs.yml` when adding new global generation options
- `PackageDiscovery` and `CheatsheetDiscovery` when adding new source types
- `DocsMetadata` when improving ordering rules
- `NavigationBuilder` when improving MkDocs nav structure
- `MintlifyDocumentation::updateHubIndex()` when improving Mintlify nav structure
- `PackagesIndexGenerator` and `CheatsheetsIndexGenerator` when changing landing page generation

Avoid adding one-off path rules inside commands. The commands should stay thin orchestration layers; format-specific behavior belongs in the documentation services and builders.
