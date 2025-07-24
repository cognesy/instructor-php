# Doctest System Documentation

## Overview

The Doctest system is a comprehensive solution for extracting, managing, and testing code examples embedded in Markdown documentation. It bridges the gap between documentation and executable code by moving large code snippets to external files while maintaining their integration with the documentation generation process.

## Problem Statement

### Documentation Challenges

Modern software documentation faces several critical challenges:

1. **Code Quality Assurance**: Code examples in documentation can become outdated, contain bugs, or fail to reflect current API usage
2. **Maintenance Overhead**: Large code blocks embedded directly in Markdown files are difficult to test, lint, and maintain
3. **Developer Experience**: Writers want to focus on documentation content rather than wrestling with complex code formatting in Markdown
4. **Testing Integration**: Code snippets should be executable and testable to ensure they work as documented
5. **File Management**: Documentation with extensive code examples becomes unwieldy and hard to navigate

### Traditional Approaches and Their Limitations

**Embedded Code Blocks**: Keeping all code directly in Markdown files leads to:
- No syntax checking or IDE support
- Difficult testing and validation
- Version drift between documentation and actual working code
- Large, hard-to-edit documentation files

**Separate Code Files**: Moving all code to separate files creates:
- Broken connection between docs and code
- Manual synchronization overhead
- Unclear organization and relationships

## Solution Overview

The Doctest system provides an elegant solution that combines the best of both approaches:

1. **Code Extraction**: Automatically extracts larger code blocks from Markdown files to standalone, testable files
2. **Intelligent Filtering**: Uses metadata-driven rules to determine which code blocks should be extracted
3. **Seamless Integration**: Maintains the connection between documentation and code through include metadata
4. **Testing Support**: Extracted files can be executed, tested, and validated like any other code
5. **Documentation Generation**: Works seamlessly with existing documentation generation tools

### Key Benefits

- **Maintainable Documentation**: Code examples stay up-to-date through testing
- **Developer Productivity**: IDE support, syntax highlighting, and debugging for extracted code
- **Quality Assurance**: Automated testing ensures examples actually work
- **Clean Separation**: Documentation focuses on explanation, code focuses on implementation
- **Backwards Compatibility**: Existing documentation workflows continue to work

## System Architecture

### Core Components

#### 1. Doctest Class (`src/Doctest/Doctest.php`)

The central entity representing an extractable code block:

```php
class Doctest {
    public function __construct(
        public ?string $id,              // Unique identifier for the code block
        public string $language,         // Programming language (php, javascript, etc.)
        public int $linesOfCode,         // Number of lines in the code block
        public string $markdownPath,     // Source Markdown file path
        public string $codePath,         // Target path for extracted code file
        public string $code,             // Raw code content
        public string $markdownTitle,    // Title from Markdown metadata
        public string $markdownDescription, // Description from Markdown metadata
        public string $caseDir,          // Target directory for extracted files
        public string $casePrefix,       // Filename prefix for extracted files
        public int $minLines,            // Minimum lines required for extraction
        public array $includedTypes,     // Allowed programming languages
    ) {}
}
```

**Key Methods**:
- `fromMarkdown(MarkdownFile $file)`: Creates Doctest instances from a Markdown file
- `toFileContent(?string $region = null)`: Generates the content for the extracted file
- `getAvailableRegions()`: Lists available regions within the code block
- `hasRegions()`: Checks if the code block contains region markers

#### 2. MarkdownFile Class (`src/Markdown/MarkdownFile.php`)

Parses and manipulates Markdown files with YAML front matter:

```php
class MarkdownFile {
    public static function fromString(string $text, string $path = ''): self;
    public function codeBlocks(): Iterator<CodeBlockNode>;
    public function metadata(string $key, mixed $default = null): mixed;
    public function withReplacedCodeBlocks(callable $replacer): self;
    public function toString(): string;
}
```

#### 3. ExtractCodeBlocks Command (`src/Doctest/Commands/ExtractCodeBlocks.php`)

The main command-line interface for the extraction process:

```bash
php bin/instructor-hub docs:extract [options]
```

#### 4. RegionExtractor Class (`src/Doctest/RegionExtractor.php`)

Handles extraction of specific regions within code blocks using special markers:

```php
class RegionExtractor {
    public static function extractRegion(string $content, string $language, string $regionName): ?string;
    public static function extractAllRegions(string $content, string $language): array;
    public static function hasRegions(string $content, string $language): bool;
    public static function getRegionNames(string $content, string $language): array;
}
```

### Metadata Configuration

The system uses YAML front matter in Markdown files to configure extraction behavior:

```yaml
---
title: "API Documentation"
description: "Complete API usage examples"
doctest_case_dir: examples/api           # Target directory
doctest_case_prefix: api_                # Filename prefix
doctest_min_lines: 2                     # Minimum lines to extract
doctest_included_types: ["php", "javascript"] # Allowed languages
---
```

### Code Block Identification

Code blocks can be identified in several ways:

1. **Explicit Fence Parameters**:
   ```markdown
   ```php id="example_auth"
   $client = new ApiClient();
   $client->authenticate('api-key-123');
   ```
   ```

2. **@doctest Annotations**:
   ```markdown
   ```php
   // @doctest id="auth_example"
   $client = new ApiClient();
   $client->authenticate('api-key-123');
   ```
   ```

3. **Auto-generated IDs**:
   Code blocks without explicit IDs receive auto-generated 4-character hexadecimal identifiers.

## Usage Guide

### Basic Extraction

#### Single File Extraction

Extract code blocks from a single Markdown file:

```bash
# Basic extraction using metadata-defined paths
php bin/instructor-hub docs:extract --source docs/api-guide.md

# Extract with custom target directory
php bin/instructor-hub docs:extract --source docs/api-guide.md --target-dir extracted/

# Preview extraction without writing files
php bin/instructor-hub docs:extract --source docs/api-guide.md --dry-run
```

#### Directory Processing

Process multiple Markdown files recursively:

```bash
# Process entire documentation directory
php bin/instructor-hub docs:extract --source-dir docs/

# Filter by file extensions
php bin/instructor-hub docs:extract --source-dir docs/ --extensions md,mdx

# Process with custom output directory
php bin/instructor-hub docs:extract --source-dir docs/ --target-dir examples/extracted/
```

### Source Modification Workflow

The `--modify-source` option transforms the original Markdown files:

```bash
# Extract and modify source files (creates backups)
php bin/instructor-hub docs:extract --source docs/api-guide.md --modify-source
```

**Before modification:**
```markdown
```php id="auth_example"
$client = new ApiClient();
$client->authenticate('api-key-123');
$response = $client->getUser(456);
```
```

**After modification:**
```markdown
```php id="auth_example" include="examples/api/api_auth_example.php"
// Code extracted - will be included from external file
```
```

**Generated file** (`examples/api/api_auth_example.php`):
```php
<?php
// @doctest id=auth_example
$client = new ApiClient();
$client->authenticate('api-key-123');
$response = $client->getUser(456);
?>
```

### Advanced Configuration

#### Custom Metadata Settings

```yaml
---
title: "HTTP Client Documentation"
description: "Complete HTTP client usage guide"
doctest_case_dir: examples/http          # Custom output directory
doctest_case_prefix: http_               # Custom filename prefix
doctest_min_lines: 3                     # Require at least 3 lines
doctest_included_types: ["php"]          # Only extract PHP code
---
```

#### Default Behavior

When metadata is not specified:
- `doctest_case_dir`: Defaults to `examples/`
- `doctest_case_prefix`: Auto-generated from filename (e.g., `01_introduction.md` → `introduction_`)
- `doctest_min_lines`: Defaults to `0` (no minimum)
- `doctest_included_types`: Must be explicitly specified

### Region Extraction

For large code blocks, you can extract specific regions:

```markdown
```php id="complete_example"
<?php

// @doctest-region-start name="setup"
$client = new ApiClient([
    'base_url' => 'https://api.example.com',
    'timeout' => 30
]);
// @doctest-region-end

// @doctest-region-start name="authentication"
$client->authenticate('api-key-123');
$client->setUserAgent('MyApp/1.0');
// @doctest-region-end

// @doctest-region-start name="usage"
$users = $client->get('/users');
foreach ($users as $user) {
    echo "User: {$user['name']}\n";
}
// @doctest-region-end
```
```

This generates multiple files:
- `complete_example.php` - Full code block
- `complete_example_setup.php` - Setup region only
- `complete_example_authentication.php` - Authentication region only  
- `complete_example_usage.php` - Usage region only

### Testing Integration

Extracted files can be integrated into your testing workflow:

```php
// PHPUnit test example
class DocumentationTest extends TestCase
{
    public function testApiExamples()
    {
        // Test extracted documentation examples
        require_once 'examples/api/api_auth_example.php';
        
        // Verify the example works as documented
        $this->assertInstanceOf(ApiClient::class, $client);
        $this->assertNotNull($response);
    }
}
```

## Command Reference

### docs:extract

Primary command for extracting code blocks from Markdown files.

#### Options

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--source` | `-s` | Single Markdown file path | Required (unless --source-dir) |
| `--source-dir` | | Directory to scan recursively | Required (unless --source) |
| `--target-dir` | `-t` | Override metadata-based target directory | Uses metadata |
| `--extensions` | `-e` | File extensions to process | `md,mdx` |
| `--dry-run` | | Preview without writing files | `false` |
| `--modify-source` | `-m` | Modify source files (creates backups) | `false` |

#### Examples

```bash
# Extract from single file
php bin/instructor-hub docs:extract -s docs/tutorial.md

# Process directory with verbose output
php bin/instructor-hub docs:extract --source-dir docs/ -v

# Extract and modify sources with backup
php bin/instructor-hub docs:extract -s docs/api.md --modify-source

# Custom target with specific extensions
php bin/instructor-hub docs:extract --source-dir docs/ --target-dir extracted/ --extensions md
```

### docs:mark

Processes Markdown files to add IDs to code snippets without explicit identifiers.

```bash
# Add IDs to code blocks and display result
php bin/instructor-hub docs:mark --source docs/tutorial.md

# Save processed content to new file
php bin/instructor-hub docs:mark --source docs/tutorial.md --output docs/tutorial-marked.md
```

## Integration with Documentation Generation

The Doctest system integrates seamlessly with existing documentation generation workflows:

### GenerateDocs Integration

When `GenerateDocs` processes Markdown files with `include` metadata, it automatically includes the content from external files:

```yaml
# Original Markdown after extraction
```php id="example" include="examples/api/api_example.php"
// Code extracted - will be included from external file
```

# Generated documentation includes the actual code
```php id="example"
$client = new ApiClient();
$client->authenticate('api-key-123');
$response = $client->getUser(456);
```

### Workflow Integration

1. **Write Documentation**: Create Markdown files with embedded code examples
2. **Extract Code**: Run `docs:extract` to move large blocks to external files
3. **Test Code**: Execute and test the extracted files
4. **Generate Docs**: Use `GenerateDocs` to create final documentation with included code
5. **Maintain**: Update extracted files and regenerate documentation as needed

## Best Practices

### Organizing Documentation

```
docs/
├── 01_getting_started.md      # doctest_case_prefix: gettingStarted_
├── 02_advanced_features.md    # doctest_case_prefix: advancedFeatures_
└── 03_troubleshooting.md      # doctest_case_prefix: troubleshooting_

examples/
├── gettingStarted_hello_world.php
├── gettingStarted_first_request.php
├── advancedFeatures_custom_client.php
└── troubleshooting_debug_mode.php
```

### Metadata Configuration

```yaml
---
title: "Getting Started Guide"
description: "Basic usage examples"
doctest_case_dir: examples/getting-started    # Organized by topic
doctest_case_prefix: basic_                    # Descriptive prefix
doctest_min_lines: 2                           # Skip trivial examples
doctest_included_types: ["php", "javascript"]  # Multi-language support
---
```

### Code Block Design

**Good - Focused and testable:**
```php id="create_client"
$client = new ApiClient([
    'api_key' => 'your-api-key',
    'base_url' => 'https://api.example.com'
]);
```

**Better - Complete and runnable:**
```php id="complete_workflow"
// @doctest-region-start name="setup"
$client = new ApiClient([
    'api_key' => getenv('API_KEY'),
    'base_url' => 'https://api.example.com'
]);
// @doctest-region-end

// @doctest-region-start name="request"
$users = $client->get('/users', [
    'limit' => 10,
    'active' => true
]);
// @doctest-region-end

// @doctest-region-start name="process"
foreach ($users as $user) {
    echo "User: {$user['name']} ({$user['email']})\n";
}
// @doctest-region-end
```

### Testing Strategy

```php
// tests/Documentation/ExamplesTest.php
class ExamplesTest extends TestCase
{
    /**
     * @dataProvider extractedExamplesProvider
     */
    public function testExtractedExamples(string $exampleFile)
    {
        $this->assertFileExists($exampleFile);
        
        // Execute the example in isolated environment
        $output = $this->executeExample($exampleFile);
        
        // Verify expected behavior
        $this->assertStringContainsString('Success', $output);
    }
    
    public function extractedExamplesProvider(): array
    {
        return [
            ['examples/gettingStarted_hello_world.php'],
            ['examples/advancedFeatures_custom_client.php'],
        ];
    }
}
```

## Troubleshooting

### Common Issues

#### 1. No Code Blocks Extracted

**Symptoms**: Command completes but no files are generated

**Causes**:
- Missing or incorrect `doctest_included_types` metadata
- Code blocks don't meet `doctest_min_lines` requirement  
- Code blocks lack identifiers (explicit or auto-generated)

**Solutions**:
```yaml
---
# Ensure configuration is present
doctest_included_types: ["php", "javascript"]  # Add required languages
doctest_min_lines: 1                           # Lower threshold if needed
---
```

#### 2. Auto-generated IDs

**Symptoms**: Code blocks receive random 4-character hex IDs

**Cause**: Missing explicit ID in fence parameters or @doctest annotation

**Solutions**:
```markdown
# Add explicit ID to fence
```php id="meaningful_name"
code here
```

# Or use @doctest annotation
```php
// @doctest id="meaningful_name"
code here
```
```

#### 3. Region Extraction Not Working

**Symptoms**: Regions are not extracted to separate files

**Cause**: Incorrect region marker syntax or language comment style

**Solution**:
```php
// Correct PHP syntax
// @doctest-region-start name="setup"
$config = [...];
// @doctest-region-end

# Correct Python syntax  
# @doctest-region-start name="setup"
config = {...}
# @doctest-region-end
```

#### 4. Include Metadata Not Generated

**Symptoms**: Source modification doesn't add include metadata

**Cause**: Code blocks don't meet extraction criteria

**Debugging**:
```bash
# Use dry-run to see what would be extracted
php bin/instructor-hub docs:extract --source file.md --dry-run -v
```

### Debugging Commands

```bash
# Verbose output shows detailed processing
php bin/instructor-hub docs:extract --source docs/file.md -v

# Dry run shows extraction plan without changes
php bin/instructor-hub docs:extract --source docs/file.md --dry-run

# Process single file to isolate issues
php bin/instructor-hub docs:extract --source docs/problematic.md --dry-run -v
```

### Validation

```bash
# Verify extracted files are valid PHP
find examples/ -name "*.php" -exec php -l {} \;

# Check generated include paths exist
grep -r "include=" docs/ | while read line; do
    file=$(echo $line | cut -d'"' -f2)
    [ -f "$file" ] || echo "Missing: $file"
done
```

## Advanced Features

### Custom File Extensions

The system automatically determines file extensions based on language:

```php
// From CodeBlockIdentifier
$extension = match($language) {
    'php' => 'php',
    'javascript', 'js' => 'js', 
    'python', 'py' => 'py',
    'typescript', 'ts' => 'ts',
    'java' => 'java',
    // ... etc
};
```

### Language-Specific Comment Syntax

Region markers use appropriate comment syntax for each language:

```php
// PHP: // @doctest-region-start
# Python: # @doctest-region-start  
<!-- HTML: <!-- @doctest-region-start -->
/* CSS: /* @doctest-region-start */
-- SQL: -- @doctest-region-start
```

### Backup Management

When using `--modify-source`, timestamped backups are created:

```
docs/api-guide.md                    # Modified file
docs/api-guide.md.20231201-143022.bak  # Backup with timestamp
```

### Path Normalization

The system handles cross-platform path separators automatically:

```php
// Input: examples/test\file.php (Windows)
// Output: examples/test/file.php (Normalized)
```

## Future Enhancements

### Planned Features

1. **Enhanced Testing Integration**
   - Built-in test runner for extracted files
   - Integration with PHPUnit and other testing frameworks
   - Automated CI/CD pipeline examples

2. **IDE Support**
   - Language server protocol integration
   - Syntax highlighting for region markers
   - Jump-to-definition between docs and extracted files

3. **Advanced Region Features**
   - Nested regions
   - Conditional region extraction
   - Region-specific metadata

4. **Documentation Generation**
   - Custom templates for extracted code
   - Automatic API documentation generation
   - Integration with OpenAPI/Swagger

### Contributing

The Doctest system is designed to be extensible. Key extension points:

- **Custom Extractors**: Implement new region extraction patterns
- **Language Support**: Add support for additional programming languages
- **Output Formats**: Create custom file content templates
- **Integration**: Build bridges to other documentation tools

## Conclusion

The Doctest system provides a robust, maintainable solution for managing code examples in documentation. By extracting code to testable files while maintaining seamless integration with documentation generation, it enables teams to maintain high-quality, up-to-date documentation that developers can trust.

The system's metadata-driven approach, combined with intelligent defaults and flexible configuration options, makes it suitable for projects of any size—from simple libraries to complex enterprise applications with extensive documentation requirements.