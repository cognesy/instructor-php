# Template: /config/docs.yaml

```yaml
# Documentation Architecture Configuration
# ========================================
# This file defines the complete navigation structure for Mintlify and MkDocs.
# The documentation generator reads this file and auto-discovers docs from packages.

meta:
  version: '1.0'
  outputs: ['mintlify', 'mkdocs']

# ============================================================================
# CATEGORIES
# ============================================================================
# Categories become Mintlify tabs. Group related packages together.
# With 20+ packages, you can't have 1 tab per package - use categories instead.

categories:
  # Primary product - gets the primary tab
  instructor:
    name: 'Instructor'
    is_primary: true
    description: 'Core structured output library'
    packages:
      - instructor

  # LLM-related infrastructure
  llm-infrastructure:
    name: 'LLM Infrastructure'
    url: 'llm'
    description: 'Low-level LLM interaction components'
    packages:
      - polyglot
      - http-client
      - messages
      - stream

  # Data modeling packages
  data-schema:
    name: 'Data & Schema'
    url: 'data'
    description: 'Data modeling and schema generation'
    packages:
      - schema
      - dynamic
      - templates
      - config

  # Framework integrations and utilities
  extensions:
    name: 'Extensions'
    url: 'ext'
    description: 'Framework integrations and utilities'
    packages:
      - laravel
      - events
      - logging
      - metrics
      - pipeline

  # Development tools
  tools:
    name: 'Development Tools'
    url: 'tools'
    description: 'Development and documentation utilities'
    packages:
      - evals
      - doctor
      - hub
      - addons
      - agent-ctrl

  # Dynamic sections (auto-generated)
  cookbook:
    name: 'Cookbook'
    url: 'cookbook'
    is_dynamic: true
    description: 'Practical examples and recipes'

  changelog:
    name: 'Changelog'
    url: 'release-notes'
    is_dynamic: true
    description: 'Version history and release notes'

# ============================================================================
# PACKAGES
# ============================================================================
# Define each package that can have documentation.
# Only packages with a docs_dir that exists will be included.

packages:
  # ---------------------------------------------------------------------------
  # INSTRUCTOR (Primary Product)
  # ---------------------------------------------------------------------------
  instructor:
    name: 'Instructor'
    description: 'Structured outputs with LLMs'
    docs_dir: 'packages/instructor/docs'
    menu_prefix: 'instructor'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      concepts:
        name: 'Concepts'
        weight: 10
      essentials:
        name: 'Essentials'
        weight: 20
      advanced:
        name: 'Advanced Features'
        weight: 30
      techniques:
        name: 'Practical Techniques'
        weight: 40
      internals:
        name: 'Internals'
        weight: 50
      misc:
        name: 'More'
        weight: 100

  # ---------------------------------------------------------------------------
  # LLM INFRASTRUCTURE
  # ---------------------------------------------------------------------------
  polyglot:
    name: 'Polyglot'
    description: 'Unified LLM API abstraction'
    docs_dir: 'packages/polyglot/docs'
    menu_prefix: 'polyglot'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      essentials:
        name: 'Essentials'
        weight: 20
      streaming:
        name: 'Streaming'
        weight: 30
      embeddings:
        name: 'Embeddings'
        weight: 40
      modes:
        name: 'Output Modes'
        weight: 50
      advanced:
        name: 'Advanced'
        weight: 60
      troubleshooting:
        name: 'Troubleshooting'
        weight: 70
      internals:
        name: 'Internals'
        weight: 80

  http-client:
    name: 'HTTP Client'
    description: 'Framework-agnostic HTTP layer'
    docs_dir: 'packages/http-client/docs'
    menu_prefix: 'http'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20
      middleware:
        name: 'Middleware'
        weight: 40
      advanced:
        name: 'Advanced'
        weight: 60
      troubleshooting:
        name: 'Troubleshooting'
        weight: 80

  messages:
    name: 'Messages'
    description: 'Message formatting and handling'
    docs_dir: 'packages/messages/docs'
    menu_prefix: 'messages'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  stream:
    name: 'Stream'
    description: 'Streaming utilities'
    docs_dir: 'packages/stream/docs'
    menu_prefix: 'stream'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  # ---------------------------------------------------------------------------
  # DATA & SCHEMA
  # ---------------------------------------------------------------------------
  schema:
    name: 'Schema'
    description: 'JSON Schema generation'
    docs_dir: 'packages/schema/docs'
    menu_prefix: 'schema'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  dynamic:
    name: 'Dynamic'
    description: 'Runtime data structures'
    docs_dir: 'packages/dynamic/docs'
    menu_prefix: 'dynamic'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  templates:
    name: 'Templates'
    description: 'Prompt templates'
    docs_dir: 'packages/templates/docs'
    menu_prefix: 'templates'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  config:
    name: 'Config'
    description: 'Configuration system'
    docs_dir: 'packages/config/docs'
    menu_prefix: 'config'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  # ---------------------------------------------------------------------------
  # EXTENSIONS
  # ---------------------------------------------------------------------------
  laravel:
    name: 'Laravel'
    description: 'Laravel framework integration'
    docs_dir: 'packages/laravel/docs'
    menu_prefix: 'laravel'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      facades:
        name: 'Facades'
        weight: 20
      advanced:
        name: 'Advanced'
        weight: 40

  events:
    name: 'Events'
    description: 'Event system'
    docs_dir: 'packages/events/docs'
    menu_prefix: 'events'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  logging:
    name: 'Logging'
    description: 'Logging system'
    docs_dir: 'packages/logging/docs'
    menu_prefix: 'logging'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  metrics:
    name: 'Metrics'
    description: 'Metrics collection'
    docs_dir: 'packages/metrics/docs'
    menu_prefix: 'metrics'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  pipeline:
    name: 'Pipeline'
    description: 'Data processing pipelines'
    docs_dir: 'packages/pipeline/docs'
    menu_prefix: 'pipeline'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  # ---------------------------------------------------------------------------
  # TOOLS
  # ---------------------------------------------------------------------------
  evals:
    name: 'Evals'
    description: 'Evaluation framework'
    docs_dir: 'packages/evals/docs'
    menu_prefix: 'evals'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  doctor:
    name: 'Doctor'
    description: 'Documentation tools'
    docs_dir: 'packages/doctor/docs'
    menu_prefix: 'doctor'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  hub:
    name: 'Hub'
    description: 'Example repository'
    docs_dir: 'packages/hub/docs'
    menu_prefix: 'hub'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

  addons:
    name: 'Addons'
    description: 'Community addons'
    docs_dir: 'packages/addons/docs'
    menu_prefix: 'addons'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      agents:
        name: 'Agents'
        weight: 20
      tools:
        name: 'Tools'
        weight: 40

  agent-ctrl:
    name: 'Agent Control'
    description: 'Agent control system'
    docs_dir: 'packages/agent-ctrl/docs'
    menu_prefix: 'agent-ctrl'
    sections:
      getting-started:
        name: 'Getting Started'
        weight: 1
      usage:
        name: 'Usage'
        weight: 20

# ============================================================================
# OUTPUT CONFIGURATION
# ============================================================================

output:
  mintlify:
    target_dir: 'docs-build'
    index_file: 'mint.json'
    file_extension: 'mdx'
    group_separator: ' \\ '

  mkdocs:
    target_dir: 'docs-mkdocs'
    config_file: 'mkdocs.yml'
    file_extension: 'md'

# ============================================================================
# DISCOVERY CONFIGURATION
# ============================================================================

discovery:
  doc_extensions: ['.md', '.mdx']
  example_filename: 'run.php'
  ignore_patterns:
    - '_draft'
    - '.wip'
    - 'README.md'
```
