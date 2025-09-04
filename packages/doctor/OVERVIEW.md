# Doctor Package Overview

**Purpose**: Documentation automation utility for extracting, testing, and generating code examples from markdown documentation.

## Core Architecture

### Four Main Components:

1. **Markdown Processing** (`src/Markdown/`)
   - `MarkdownFile`: Parses markdown with YAML frontmatter, extracts code blocks and metadata
   - AST-based parser with lexer/token system
   - Visitor pattern for transformations (`ReplaceCodeBlockByCallable`, `ToString`)
   - Code block manipulation with metadata extraction

2. **Doctest System** (`src/Doctest/`)
   - `DoctestFile`: Extracts code blocks from markdown, converts to testable files
   - Region-based code extraction using special comments (`@doctest-region-start name=example` / `@doctest-region-end`)
   - Language-agnostic code generation with file templates
   - Batch processing for multiple markdown files
   - Filtering based on language, minimum lines, IDs

3. **Documentation Generation** (`src/Docgen/`)
   - Supports both Mintlify and MkDocs documentation formats
   - Handles package docs, examples, and navigation index generation
   - Inlines external code blocks from separate files
   - Manages release notes and dynamic navigation groups

4. **Code Screenshot Generation** (`src/Freeze/`)
   - Creates visual screenshots of code snippets
   - Configurable themes, fonts, and styling options
   - Multiple execution backends (shell, exec, Symfony Process)

## Key Features

- **Code Block Extraction**: Identifies code blocks with IDs (`@doctest id=example`) in markdown, extracts to separate files for testing
- **Region Support**: Extracts specific code regions using comment markers (`@doctest-region-start name=region`)
- **Validation**: Ensures extracted code files exist and match markdown blocks
- **Multi-language**: Supports PHP, JavaScript, Python, etc. with appropriate file extensions and comment styles
- **Metadata Processing**: YAML frontmatter parsing with doctest configuration
- **Template System**: Language-specific file templates for generated code
- **Build Pipeline**: Automated docs generation with file copying, renaming (.md → .mdx)
- **Event System**: Domain events for tracking extraction and validation progress

## CLI Commands

### Doctest Commands
- `mark`: Process single Markdown file and add IDs to code snippets
- `mark-dir`: Recursively process Markdown files in a directory and add IDs to code snippets
- `extract`: Extract code blocks from Markdown files to target directory
- `validate`: Validate extracted code blocks and list missing/wrong paths

### Documentation Generation Commands
- `generate-mintlify`: Generate Mintlify-compatible documentation
- `generate-mkdocs`: Generate MkDocs-compatible documentation
- `generate-examples`: Generate examples only
- `generate-packages`: Generate package documentation only
- `clear-mintlify`: Clean Mintlify build directory
- `clear-mkdocs`: Clean MkDocs build directory

### Code Screenshot Commands
- `freeze`: Create visual screenshots of code snippets with configurable styling

## Configuration

### Metadata in markdown frontmatter:
- `doctest_case_dir`: Output directory for extracted code (default: './tests')
- `doctest_case_prefix`: Filename prefix for generated files (default: 'test_')
- `doctest_min_lines`: Minimum lines required for extraction (default: 1)
- `doctest_included_types`: Array of programming languages to include (default: ['php'])

### Code Block Annotations:
- `@doctest id=example`: Assigns unique ID to code block for extraction
- `@doctest-region-start name=region`: Marks beginning of extractable region
- `@doctest-region-end`: Marks end of extractable region

### Metadata Precedence:
1. YAML frontmatter (highest priority)
2. Code block fence parameters
3. Inline `@doctest` annotations (lowest priority)

## Dependencies

- Symfony Console, Filesystem, YAML
- nikic/iter for functional iteration
- Custom utilities from `cognesy/instructor-utils`

## Use Case

Enables documentation-driven development where code examples in docs are automatically extracted, tested externally, and kept in sync with the documentation build process.