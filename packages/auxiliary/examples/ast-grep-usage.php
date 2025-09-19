<?php
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Cognesy\Auxiliary\AstGrep\AstGrep;
use Cognesy\Auxiliary\AstGrep\PatternBuilder;
use Cognesy\Auxiliary\AstGrep\UsageFinder;
use Cognesy\Auxiliary\AstGrep\Enums\Language;

echo "=== AstGrep PHP API Examples ===\n\n";

// Example 1: Basic search with AstGrep
echo "1. Basic Pattern Search\n";
echo "-----------------------\n";

$astGrep = new AstGrep(Language::PHP);
$pattern = PatternBuilder::create()
    ->classInstantiation('ChatState')
    ->build();

$results = $astGrep->search($pattern, 'packages/addons');

echo "Found {$results->count()} instantiations of ChatState:\n";
foreach ($results as $result) {
    echo "  - {$result->getRelativePath('packages/addons')}:{$result->line}\n";
    echo "    {$result->getMatchPreview()}\n";
}
echo "\n";

// Example 2: Using UsageFinder for comprehensive class analysis
echo "2. Comprehensive Class Usage Analysis\n";
echo "--------------------------------------\n";

$finder = new UsageFinder();
$classUsages = $finder->findClassUsages('ChatState', 'packages/addons');

echo "ChatState usage analysis:\n";
echo "  - Instantiations: {$classUsages->instantiations->count()}\n";
echo "  - Static calls: {$classUsages->staticCalls->count()}\n";
echo "  - Extensions: {$classUsages->extensions->count()}\n";
echo "  - Implementations: {$classUsages->implementations->count()}\n";
echo "  - Imports: {$classUsages->imports->count()}\n";
echo "  - Total usages: {$classUsages->getTotalUsageCount()}\n";
echo "  - Is used: " . ($classUsages->hasAnyUsage() ? 'Yes' : 'No') . "\n";
echo "\n";

// Example 3: Method usage analysis
echo "3. Method Usage Analysis\n";
echo "------------------------\n";

$methodUsages = $finder->findMethodUsages('ChatState', 'withVariable', 'packages/addons');

echo "ChatState::withVariable usage analysis:\n";
echo "  - Instance calls: {$methodUsages->instanceCalls->count()}\n";
echo "  - Static calls: {$methodUsages->staticCalls->count()}\n";
echo "  - Total usages: {$methodUsages->getTotalUsageCount()}\n";
echo "  - Is used: " . ($methodUsages->hasAnyUsage() ? 'Yes' : 'No') . "\n";

if ($methodUsages->instanceCalls->isNotEmpty()) {
    echo "  Instance calls found in:\n";
    foreach ($methodUsages->instanceCalls->getFiles() as $file) {
        echo "    - " . basename($file) . "\n";
    }
}
echo "\n";

// Example 4: Building custom patterns
echo "4. Custom Pattern Examples\n";
echo "--------------------------\n";

$patterns = [
    'Method calls' => PatternBuilder::create()->methodCall('execute'),
    'Static calls' => PatternBuilder::create()->staticMethodCall('Factory', 'create'),
    'Try-catch blocks' => PatternBuilder::create()->tryCatch(),
    'Foreach loops' => PatternBuilder::create()->foreachLoop(),
    'Return statements' => PatternBuilder::create()->returnStatement(),
];

foreach ($patterns as $name => $pattern) {
    echo "  $name pattern: {$pattern->build()}\n";
}
echo "\n";

// Example 5: Grouped results
echo "5. Grouped Search Results\n";
echo "-------------------------\n";

$results = $finder->findInstanceMethodCalls('process', 'packages/addons/src');

if ($results->isNotEmpty()) {
    $byFile = $results->groupByFile();
    echo "Method 'process' calls grouped by file:\n";

    foreach ($byFile as $file => $fileResults) {
        echo "  " . basename($file) . " (" . count($fileResults) . " calls)\n";
        foreach ($fileResults as $result) {
            echo "    Line {$result->line}: {$result->getMatchPreview(60)}\n";
        }
    }
} else {
    echo "No 'process' method calls found.\n";
}
echo "\n";

// Example 6: Quick usage check
echo "6. Quick Usage Checks\n";
echo "---------------------\n";

$classesToCheck = [
    'StructuredOutput',
    'ChatState',
    'NonExistentClass',
];

foreach ($classesToCheck as $className) {
    $isUsed = $finder->isClassUsed($className, 'packages');
    echo "  Is '$className' used? " . ($isUsed ? '✓ Yes' : '✗ No') . "\n";
}

echo "\nDone!\n";