# Repository Contents

This document provides an overview of the essential directories and important files at the root level of the Instructor PHP monorepo.

## Essential Directories

### `packages/`
Contains all independent PHP packages that make up the Instructor ecosystem. Each package has its own `composer.json`, tests, and documentation. Current packages include:
- **Core**: `instructor`, `config`, `events`, `messages`, `utils`, `schema`, `templates`
- **Extended functionality**: `addons`, `auxiliary`, `polyglot`, `setup`, `hub`, `tell`, `dynamic`, `stream`
- **Development tools**: `evals`, `experimental`, `doctor`, `doctools`
- **Agent control**: `agent-ctrl` - Unified CLI bridge for code agents
- **Agents SDK**: `agents` - SDK for building custom AI agents
- **Framework integration**: `laravel`, `symfony`
- **Observability**: `metrics`, `logging`
- **HTTP client**: `http-client`
- **HTTP pooling**: `http-pool`
- **Pipeline processing**: `pipeline`
- **Sandbox**: `sandbox` - Safe code execution environment

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
- `make-package` / `make-package-enhanced` - Generate new package structure

### `docs/`
Documentation website skeleton and root-level files. These are merged with documentation files from `./packages/*/docs/` to build the complete documentation distribution in `./builds/docs-build/`.

### `bin/`
Executable CLI tools:
- `instructor-hub` - List, view, and execute runnable code samples from the `./examples/` folder
- `tell` - Interactive CLI assistant
- `instructor-setup` - Project setup wizard
- `instructor-docs` - Documentation generation tool

### `builds/`
Generated build artifacts:
- `docs-build/` - Generated Mintlify documentation (built from `./docs/` + `./packages/*/docs/`)
- `docs-mkdocs/` - Generated MkDocs documentation
- `docs-site/` - Final documentation site output

### `evals/`
Evaluation and testing frameworks for model performance assessment with different complexity levels and extraction scenarios.

### `research/`
Development notes, design documents, architecture explorations, and planning materials organized by date and topic.

### `data/`
Data files used by the project.

### `src/`
Root-level PHP source files (e.g., polyfills shared across packages).

## Important Root Files

### Development Files
- `composer.json` / `composer.lock` - Main project dependencies and autoloading
- `phpstan.neon` / `phpstan-baseline.neon` / `phpstan-unused.neon` / `phpstan.neon.dist` - Static analysis configurations
- `psalm.xml` - Psalm static analysis configuration
- `phpunit.xml` - Test configuration
- `phpbench.json` - Benchmark configuration
- `mago.toml` - Mago tool configuration
- `pyproject.toml` / `pyrightconfig.json` / `uv.lock` - Python tooling (doc generation scripts)
- `requirements-doc.txt` - Python dependencies for documentation tools

### Documentation
- `README.md` - Main project documentation and getting started guide
- `CONTRIBUTING.md` - Comprehensive guide for contributors and developers
- `AGENTS.md` - Key reference files for AI agents working in this codebase
- `PACKAGES.md` - Strategic overview of the package architecture
- `LICENSE` - Project license information

### Configuration
- `packages.json` - Centralized package configuration for monorepo management
- `package-config.example.json` / `package-config-enhanced.example.json` - Templates for creating new packages
- `mkdocs.yml` - Documentation site configuration

## Supporting Directories
- `vendor/` - Composer dependencies
- `tmp/` - Temporary files and build artifacts
- `tests/` - Root-level test files and configuration
- `test-matrix/` - Test matrix configuration files
