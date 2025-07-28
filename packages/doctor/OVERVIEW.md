# Doctor Package Overview

**Purpose**: Documentation automation utility for extracting, testing, and generating code examples from markdown documentation.

## Core Architecture

### Three Main Components:

1. **Markdown Processing** (`src/Markdown/`)
   - `MarkdownFile`: Parses markdown with YAML frontmatter, extracts code blocks and metadata
   - AST-based parser with lexer/token system
   - Visitor pattern for transformations (`ReplaceCodeBlockByCallable`, `ToString`)
   - Code block manipulation with metadata extraction

2. **Doctest System** (`src/Doctest/`)
   - `DoctestFile`: Extracts code blocks from markdown, converts to testable files
   - Region-based code extraction using special comments (e.g., `// region:example`)
   - Language-agnostic code generation with file templates
   - Batch processing for multiple markdown files
   - Filtering based on language, minimum lines, IDs

3. **Documentation Generation** (`src/Docgen/`)
   - `MintlifyDocumentation`: Generates Mintlify-compatible documentation
   - Handles package docs, examples, and navigation index generation
   - Inlines external code blocks from separate files
   - Manages release notes and dynamic navigation groups

## Key Features

- **Code Block Extraction**: Identifies code blocks with IDs in markdown, extracts to separate files for testing
- **Region Support**: Extracts specific code regions using comment markers
- **Multi-language**: Supports PHP, JavaScript, Python, etc. with appropriate file extensions
- **Metadata Processing**: YAML frontmatter parsing with doctest configuration
- **Template System**: Language-specific file templates for generated code
- **Build Pipeline**: Automated docs generation with file copying, renaming (.md → .mdx)

## CLI Commands

Via `Docs` console application:
- `generate-docs`: Full documentation generation
- `generate-examples`: Example-only generation  
- `generate-packages`: Package docs only
- `clear-docs`: Clean build directory
- `mark-snippets`: Process individual files
- `extract-codeblocks`: Extract code for testing

## Configuration

Metadata in markdown frontmatter:
- `doctest_case_dir`: Output directory for extracted code
- `doctest_case_prefix`: Filename prefix for generated files
- `doctest_min_lines`: Minimum lines required for extraction
- `doctest_included_types`: Array of programming languages to include

## Dependencies

- Symfony Console, Filesystem, YAML
- webuni/front-matter for YAML parsing
- nikic/iter for functional iteration
- Custom utilities from `cognesy/instructor-utils`

## Use Case

Enables documentation-driven development where code examples in docs are automatically extracted, tested externally, and kept in sync with the documentation build process.