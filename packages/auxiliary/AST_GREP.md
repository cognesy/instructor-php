# AstGrep - Structural Code Analysis for PHP

The `packages/auxiliary/src/AstGrep` module provides a comprehensive PHP API for working with [ast-grep](https://ast-grep.github.io/), a fast structural search and replace tool. This tool is particularly useful for code analysis, refactoring, and finding class/method usage patterns.

## Overview

ast-grep is a syntax-aware search tool that understands code structure rather than just text patterns. Our PHP API wraps ast-grep to provide:

- **Class Usage Analysis** - Find where classes are instantiated, extended, or imported
- **Method Usage Detection** - Locate all calls to specific methods (instance and static)
- **Structural Code Search** - Pattern-based searching that understands PHP syntax
- **Refactoring Support** - Find code patterns that need updating
- **Dead Code Detection** - Identify unused classes and methods

## Core Components

### 1. AstGrep Class
The main interface for executing ast-grep searches.

```php
use Cognesy\Auxiliary\AstGrep\AstGrep;
use Cognesy\Auxiliary\AstGrep\Enums\Language;

$astGrep = new AstGrep(Language::PHP, workingDirectory: '/path/to/project');
$results = $astGrep->search('new UserModel($$$)', 'src/');
```

**Key Methods:**
- `search(string $pattern, string $path)` - Execute pattern search
- `searchWithRule(string $ruleFile, string $path)` - Use YAML rule file
- `replace(string $pattern, string $replacement, string $path)` - Find and replace
- `isAvailable()` - Check if ast-grep is installed

### 2. PatternBuilder Class
Fluent API for building common PHP search patterns without writing raw ast-grep syntax.

```php
use Cognesy\Auxiliary\AstGrep\PatternBuilder;

// Class instantiation
$pattern = PatternBuilder::create()->classInstantiation('UserModel');
// Result: new UserModel($$$)

// Method calls
$pattern = PatternBuilder::create()->methodCall('save');
// Result: $OBJ->save($$$)

// Static method calls
$pattern = PatternBuilder::create()->staticMethodCall('Factory', 'create');
// Result: Factory::create($$$)
```

**Supported Patterns:**
- Class instantiation, extension, implementation
- Method calls (instance and static)
- Property access
- Function calls
- Control structures (if, foreach, try-catch)
- Language constructs (use, namespace, return, throw)

### 3. UsageFinder Class
High-level API specifically designed for analyzing class and method usage across a codebase.

```php
use Cognesy\Auxiliary\AstGrep\UsageFinder;

$finder = new UsageFinder();

// Quick usage checks
$isUsed = $finder->isClassUsed('ObsoleteClass', 'src/');
$isMethodUsed = $finder->isMethodUsed('UserModel', 'obsoleteMethod', 'src/');

// Comprehensive analysis
$classUsages = $finder->findClassUsages('UserModel', 'src/');
$methodUsages = $finder->findMethodUsages('UserModel', 'save', 'src/');
```

**Usage Analysis Methods:**
- `isClassUsed()` / `isMethodUsed()` - Boolean checks
- `findClassUsages()` - Comprehensive class analysis
- `findMethodUsages()` - Comprehensive method analysis
- `findClassInstantiations()`, `findStaticMethodCalls()`, etc. - Specific pattern searches

### 4. Result Objects

#### SearchResult
Represents a single search match with metadata.

```php
$result = new SearchResult(
    file: 'src/Models/User.php',
    line: 42,
    match: 'new UserModel($data)',
    context: []
);

echo $result->getRelativePath('src/'); // Models/User.php
echo $result->getMatchPreview(50);     // Truncated match for display
```

#### SearchResults
Collection of SearchResult objects with powerful filtering and grouping capabilities.

```php
$results = $astGrep->search($pattern, 'src/');

// Collection operations
echo $results->count();
$filtered = $results->filter(fn($r) => str_contains($r->file, 'Model'));
$sorted = $results->sortByFile();

// Grouping
$byFile = $results->groupByFile();
$byDirectory = $results->groupByDirectory();

// Export
$json = $results->toJson();
$array = $results->toArray();
```

### 5. Usage Analysis Results

#### ClassUsages
Comprehensive analysis of how a class is used throughout the codebase.

```php
$usages = $finder->findClassUsages('UserModel', 'src/');

echo "Total usages: {$usages->getTotalUsageCount()}\n";
echo "Instantiations: {$usages->instantiations->count()}\n";
echo "Static calls: {$usages->staticCalls->count()}\n";
echo "Extensions: {$usages->extensions->count()}\n";
echo "Implementations: {$usages->implementations->count()}\n";
echo "Imports: {$usages->imports->count()}\n";

if ($usages->hasAnyUsage()) {
    $allResults = $usages->getAllResults(); // Combined SearchResults
}
```

#### MethodUsages
Analysis of method usage patterns.

```php
$usages = $finder->findMethodUsages('UserModel', 'save', 'src/');

echo "Instance calls: {$usages->instanceCalls->count()}\n";
echo "Static calls: {$usages->staticCalls->count()}\n";
echo "Total: {$usages->getTotalUsageCount()}\n";

if ($usages->hasAnyUsage()) {
    foreach ($usages->getAllResults() as $result) {
        echo "{$result->file}:{$result->line}\n";
    }
}
```

## Command Line Interface

A CLI tool is provided for quick searches and automation:

```bash
# Basic usage
packages/auxiliary/bin/ast-grep-php <command> <arguments>

# Find class usages
packages/auxiliary/bin/ast-grep-php class ChatState packages/addons/

# Find method usages
packages/auxiliary/bin/ast-grep-php method ChatState withVariable packages/

# Boolean checks (exit code 0 = used, 1 = not used)
packages/auxiliary/bin/ast-grep-php check-class UnusedClass src/
packages/auxiliary/bin/ast-grep-php check-method SomeClass unusedMethod src/
```

**CLI Output Example:**
```
Analyzing usage of class 'ChatState' in 'packages/addons/'...

✓ Class 'ChatState' is used:
  - Instantiations: 24
  - Static calls: 0
  - Extensions: 0
  - Implementations: 0
  - Imports: 0
  - Total: 24 usages

Instantiations found in:
  packages/addons/tests/Unit/Chat/CoordinatorsTest.php:24 - $state = new ChatState();
  packages/addons/tests/Unit/Chat/ParticipantsTest.php:16 - $step = $p->act(new ChatState());
  ...
```

## Common Use Cases

### 1. Dead Code Detection
Find unused classes and methods before refactoring:

```php
$finder = new UsageFinder();

$classesToCheck = ['LegacyHelper', 'OldUtility', 'DeprecatedService'];
foreach ($classesToCheck as $class) {
    if (!$finder->isClassUsed($class, 'src/')) {
        echo "✗ $class is unused and can be removed\n";
    }
}

// Check specific methods
if (!$finder->isMethodUsed('DatabaseConnection', 'legacyConnect', 'src/')) {
    echo "✗ DatabaseConnection::legacyConnect is unused\n";
}
```

### 2. Impact Analysis
Understand the scope of changes before modifying code:

```php
$usages = $finder->findMethodUsages('UserService', 'deleteUser', 'src/');

echo "Changing UserService::deleteUser will affect {$usages->getTotalUsageCount()} locations:\n";
foreach ($usages->getAllResults()->getFiles() as $file) {
    echo "- $file\n";
}

// Analyze by usage type
if ($usages->staticCalls->isNotEmpty()) {
    echo "Static calls (may need interface changes):\n";
    foreach ($usages->staticCalls as $call) {
        echo "  {$call->file}:{$call->line}\n";
    }
}
```

### 3. Migration and Refactoring
Find patterns that need updating during migrations:

```php
$astGrep = new AstGrep();

// Find old array syntax
$results = $astGrep->search('array($$$)', 'src/');
echo "Found {$results->count()} uses of old array() syntax to update\n";

// Find deprecated function calls
$results = $astGrep->search('mysql_query($$$)', 'src/');
foreach ($results->groupByFile() as $file => $matches) {
    echo "$file has " . count($matches) . " deprecated mysql_query calls\n";
}

// Find specific patterns to replace
$results = $astGrep->search('Request::get($$$)', 'src/');
echo "Found {$results->count()} Request::get() calls to update to new API\n";
```

### 4. Architecture Analysis
Understand code structure and dependencies:

```php
// Find all service injections
$results = $astGrep->search('public function __construct($$$Service $$$)', 'src/');
echo "Found {$results->count()} service injections\n";

// Find all event dispatching
$results = $astGrep->search('$dispatcher->dispatch($$$)', 'src/');
$byDirectory = $results->groupByDirectory();
foreach ($byDirectory as $dir => $matches) {
    echo "$dir: " . count($matches) . " event dispatches\n";
}

// Find specific design patterns
$results = $astGrep->search('class $CLASS implements ObserverInterface', 'src/');
echo "Found {$results->count()} Observer pattern implementations\n";
```

### 5. Code Quality Analysis
Identify potential issues or patterns:

```php
// Find direct database queries (should use repository pattern)
$results = $astGrep->search('DB::select($$$)', 'src/');
$controllerQueries = $results->filter(fn($r) => str_contains($r->file, 'Controller'));
echo "Found {$controllerQueries->count()} direct DB queries in controllers\n";

// Find hardcoded strings that should be configuration
$results = $astGrep->search("'http://localhost'", 'src/');
if ($results->isNotEmpty()) {
    echo "Found hardcoded localhost URLs in:\n";
    foreach ($results as $result) {
        echo "  {$result->file}:{$result->line}\n";
    }
}

// Find exception handling patterns
$results = $astGrep->search('catch (Exception $e) { $$$ }', 'src/');
$emptyHandlers = $results->filter(fn($r) => str_contains($r->match, '// TODO'));
echo "Found {$emptyHandlers->count()} empty exception handlers\n";
```

## Integration Examples

### With CI/CD Pipeline
Use the CLI tool in continuous integration:

```bash
#!/bin/bash
# check-unused-code.sh

echo "Checking for unused classes..."
UNUSED_CLASSES=(
    "LegacyHelper"
    "OldUtility"
    "DeprecatedService"
)

for class in "${UNUSED_CLASSES[@]}"; do
    if packages/auxiliary/bin/ast-grep-php check-class "$class" src/; then
        echo "❌ $class is still in use - remove from deprecation list"
        exit 1
    else
        echo "✅ $class is unused - safe to remove"
    fi
done
```

### With PHPStan Extension
Create custom PHPStan rules using the API:

```php
class UnusedClassRule implements Rule
{
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        $finder = new UsageFinder();
        $className = $node->name->name;

        if (!$finder->isClassUsed($className, 'src/')) {
            return [
                RuleErrorBuilder::message("Class $className is unused")
                    ->build()
            ];
        }

        return [];
    }
}
```

### With Automated Refactoring
Combine search and replace for large-scale refactoring:

```php
$astGrep = new AstGrep();

// Find all instances of old pattern
$results = $astGrep->search('Config::get($KEY)', 'src/');

if ($results->isNotEmpty()) {
    echo "Found {$results->count()} Config::get() calls to replace\n";

    // Use replace functionality (if available) or manual processing
    foreach ($results->groupByFile() as $file => $matches) {
        echo "Updating $file ({count($matches)} matches)\n";
        // Process file for replacement
    }
}
```

## Performance Considerations

- **ast-grep is fast** - Can search large codebases in seconds
- **Pattern complexity** - Simple patterns are faster than complex ones
- **Path specificity** - Search specific directories rather than entire projects when possible
- **Result processing** - Use filtering and grouping efficiently

```php
// Efficient: Search specific areas
$results = $finder->findClassUsages('UserModel', 'src/Models/');

// Less efficient: Search everything then filter
$results = $finder->findClassUsages('UserModel', './')
    ->filter(fn($r) => str_contains($r->file, 'Models/'));
```

## Requirements

1. **ast-grep** must be installed and available in PATH
2. **PHP 8.2+** for the API
3. **Proper file permissions** for reading source files

Install ast-grep:
```bash
# Using cargo
cargo install ast-grep

# Using npm
npm install -g @ast-grep/cli

# Using homebrew
brew install ast-grep
```

## Error Handling

The API handles common error scenarios:

```php
try {
    $astGrep = new AstGrep();
    $results = $astGrep->search($pattern, 'src/');
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'ast-grep is not available')) {
        echo "Please install ast-grep first\n";
    }
}

// Check availability before use
$astGrep = new AstGrep();
if (!$astGrep->isAvailable()) {
    throw new RuntimeException('ast-grep not found in PATH');
}
```

## Testing

The module includes comprehensive tests:

```bash
# Run all AstGrep tests
composer test packages/auxiliary/tests/Unit/AstGrep/

# Run specific test file
php vendor/bin/pest packages/auxiliary/tests/Unit/AstGrep/PatternBuilderTest.php
```

Test coverage includes:
- Pattern building for all supported PHP constructs
- Search result parsing and manipulation
- Collection operations (filtering, grouping, sorting)
- Error handling and edge cases

This comprehensive tool provides powerful capabilities for code analysis, refactoring, and maintenance tasks while maintaining a simple, type-safe PHP API.