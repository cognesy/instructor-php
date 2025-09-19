# AstGrep PHP API

A pragmatic PHP API for working with [ast-grep](https://ast-grep.github.io/) - a fast structural search and replace tool for code.

## Features

- ✅ **Simple API** - Easy-to-use classes for searching and pattern building
- ✅ **Type-safe** - Full PHP 8.2+ typing with enums and readonly classes
- ✅ **Rich Results** - Structured search results with filtering and grouping
- ✅ **Usage Analysis** - Find class and method usages across your codebase
- ✅ **CLI Tool** - Command-line interface for quick searches
- ✅ **Pattern Builder** - Fluent API for building ast-grep patterns
- ✅ **Tested** - Comprehensive test suite

## Quick Start

### Basic Search

```php
use Cognesy\Auxiliary\AstGrep\AstGrep;
use Cognesy\Auxiliary\AstGrep\PatternBuilder;
use Cognesy\Auxiliary\AstGrep\Enums\Language;

$astGrep = new AstGrep(Language::PHP);

// Find class instantiations
$pattern = PatternBuilder::create()
    ->classInstantiation('UserModel')
    ->build();

$results = $astGrep->search($pattern, 'src/');

foreach ($results as $result) {
    echo "{$result->file}:{$result->line} - {$result->getMatchPreview()}\n";
}
```

### Usage Analysis

```php
use Cognesy\Auxiliary\AstGrep\UsageFinder;

$finder = new UsageFinder();

// Check if a class is used anywhere
if ($finder->isClassUsed('ObsoleteClass', 'src/')) {
    echo "Class is still in use!\n";
}

// Comprehensive class usage analysis
$usages = $finder->findClassUsages('UserModel', 'src/');
echo "Found {$usages->getTotalUsageCount()} usages:\n";
echo "- Instantiations: {$usages->instantiations->count()}\n";
echo "- Static calls: {$usages->staticCalls->count()}\n";
echo "- Extensions: {$usages->extensions->count()}\n";

// Method usage analysis
$methodUsages = $finder->findMethodUsages('UserModel', 'save', 'src/');
if ($methodUsages->hasAnyUsage()) {
    echo "Method UserModel::save is used {$methodUsages->getTotalUsageCount()} times\n";
}
```

### Pattern Builder

```php
use Cognesy\Auxiliary\AstGrep\PatternBuilder;

// Method calls
$pattern = PatternBuilder::create()->methodCall('execute');
// Result: $OBJ->execute($$$)

// Static method calls
$pattern = PatternBuilder::create()->staticMethodCall('Factory', 'create');
// Result: Factory::create($$$)

// Class definitions
$pattern = PatternBuilder::create()->classExtends('BaseController');
// Result: class $CLASS extends BaseController

// Custom patterns
$pattern = PatternBuilder::create()->custom('match ($VALUE) { $$$ }');
```

### Working with Results

```php
$results = $astGrep->search($pattern, 'src/');

// Basic operations
echo "Found {$results->count()} matches\n";
echo "First match: {$results->first()->file}:{$results->first()->line}\n";

// Filtering and grouping
$phpFiles = $results->filter(fn($r) => str_ends_with($r->file, '.php'));
$byFile = $results->groupByFile();
$byDirectory = $results->groupByDirectory();

// Get unique files/directories
$uniqueFiles = $results->getFiles();
$directories = $results->getDirectories();

// Sorting
$sortedByFile = $results->sortByFile();
$sortedByLine = $results->sortByLine();

// Export
$json = $results->toJson();
$array = $results->toArray();
```

## CLI Tool

The package includes a command-line tool for quick searches:

```bash
# Find class usages
packages/auxiliary/bin/ast-grep-php class ChatState packages/

# Find method usages
packages/auxiliary/bin/ast-grep-php method ChatState withVariable packages/

# Quick checks (exits 0 if used, 1 if not)
packages/auxiliary/bin/ast-grep-php check-class UnusedClass src/
packages/auxiliary/bin/ast-grep-php check-method SomeClass unusedMethod src/
```

## Use Cases

### 1. Code Cleanup
Find unused classes and methods before refactoring:

```php
$finder = new UsageFinder();

$classesToCheck = ['LegacyHelper', 'OldUtility', 'DeprecatedService'];
foreach ($classesToCheck as $class) {
    if (!$finder->isClassUsed($class, 'src/')) {
        echo "✗ $class is unused and can be removed\n";
    }
}
```

### 2. Impact Analysis
Understand the impact of changing a method:

```php
$usages = $finder->findMethodUsages('DatabaseConnection', 'connect', 'src/');
echo "Changing DatabaseConnection::connect will affect:\n";
foreach ($usages->getAllResults()->getFiles() as $file) {
    echo "- $file\n";
}
```

### 3. Migration Assistance
Find patterns that need updating:

```php
// Find old array syntax
$results = $astGrep->search('array($$$)', 'src/');
echo "Found {$results->count()} uses of old array() syntax\n";

// Find deprecated function calls
$results = $astGrep->search('mysql_query($$$)', 'src/');
if ($results->isNotEmpty()) {
    echo "Found deprecated mysql_query calls to update\n";
}
```

### 4. Architecture Analysis
Understand code structure and dependencies:

```php
// Find all service injections
$results = $astGrep->search('public function __construct(Service $$$)', 'src/');

// Find all event dispatching
$results = $astGrep->search('$dispatcher->dispatch($$$)', 'src/');

// Find specific design patterns
$results = $astGrep->search('class $CLASS implements ObserverInterface', 'src/');
```

## Requirements

- PHP 8.2+
- [ast-grep](https://ast-grep.github.io/) installed and available in PATH

## Installation

ast-grep must be installed separately. See the [official installation guide](https://ast-grep.github.io/guide/quick-start.html).

## API Reference

### Classes

- **`AstGrep`** - Main class for executing searches
- **`PatternBuilder`** - Fluent API for building search patterns
- **`UsageFinder`** - High-level API for finding class/method usages
- **`SearchResult`** - Individual search result with file, line, and match
- **`SearchResults`** - Collection of search results with filtering/grouping
- **`Language`** - Supported programming languages enum

### Enums

- **`Language`** - PHP, JavaScript, TypeScript, Python, Java, etc.

The API prioritizes simplicity and type safety while providing comprehensive functionality for code analysis and refactoring tasks.