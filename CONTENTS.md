# Repository Contents

This document provides an overview of the essential directories and important files at the root level of the Instructor PHP monorepo.

## Essential Directories

### `packages/`
Contains all independent PHP packages that make up the Instructor ecosystem. Each package has its own `composer.json`, tests, and documentation. Current packages include:
- **Core**: `instructor`, `config`, `events`, `messages`, `utils`, `schema`, `templates`
- **Extended functionality**: `addons`, `auxiliary`, `polyglot`, `setup`, `hub`, `tell`, `dynamic`
- **Development tools**: `evals`, `experimental`, `doctor`
- **Agent control**: `agent-ctrl` - Unified CLI bridge for code agents
- **Observability**: `metrics` - metrics collection and export
- **HTTP client**: `http-client`
- **Pipeline processing**: `pipeline`

### `examples/`
Comprehensive collection of working examples demonstrating various features, complexity levels, API integrations, and prompting techniques. Examples are organized by topic and difficulty to help users learn and implement different patterns.

### `scripts/`
Development and maintenance automation scripts:
- `publish-ver.sh` - Release management and version publishing
- `sync-ver.sh` - Synchronize versions across all packages
- `load-packages.sh` - Load centralized package configuration
- `generate-split-matrix.sh` - Generate GitHub Actions matrix from package config
- `update-split-yml.sh` - Update split.yml with centralized package configuration
- `run-all-tests.sh` - Execute tests for all packages
- `composer-*-all.sh` - Composer operations across packages
- `create-package.php` - Generate new package structure

- More details in `docs-internal/development/SCRIPTS.md` file.

### `docs/`
Documentation website skeleton and root-level files. These are merged with documentation files from `./packages/*/docs/` to build the complete documentation distribution in `./docs-build/`.

### `bin/`
Executable CLI tools:
- `instructor-hub` - List, view, and execute runnable code samples from the `./examples/` folder
- `tell` - Interactive CLI assistant
- `instructor-setup` - Project setup wizard

### `evals/`
Evaluation and testing frameworks for model performance assessment with different complexity levels and extraction scenarios.

### `config/`
Default configuration files for various components (debug, HTTP, LLM, prompts, structured output).

## Important Root Files

### Development Files
- `composer.json` / `composer.lock` - Main project dependencies and autoloading
- `phpstan.neon` / `psalm.xml` - Static analysis configurations
- `phpunit.xml` - Test configuration

### Documentation
- `README.md` - Main project documentation and getting started guide
- `CONTRIBUTOR_GUIDE.md` - Comprehensive guide for contributors and developers
- `docs-internal/development/SCRIPTS.md` - Overview of the available scripts
- `LICENSE` - Project license information

### Configuration
- `packages.json` - Centralized package configuration for monorepo management
- `package-config.example.json` - Template for creating new packages
- `mkdocs.yml` - Documentation site configuration

## Archive and Legacy
- `archived/` - Legacy code and deprecated implementations
- `notes/` - Development notes, ideas, and planning documents
- `scratchpad/` - Experimental code and proof-of-concepts

## Supporting Directories
- `vendor/` - Composer dependencies
- `tmp/` - Temporary files and build artifacts
- `docs-build/` - Generated documentation files (built from `./docs/` + `./packages/*/docs/`)
- `prompts/` - Template files for prompts (Twig, Blade)
- `tests/` - Root-level test files and configuration