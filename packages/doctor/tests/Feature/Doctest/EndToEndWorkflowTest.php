<?php

use Cognesy\Doctor\Doctest\DoctestFile;
use Cognesy\Doctor\Doctest\Internal\MarkdownInfo;
use Cognesy\Doctor\Markdown\MarkdownFile;

describe('End-to-End Doctest Workflow', function () {
    describe('complete extraction and inclusion workflow', function () {
        it('processes markdown from creation to final inclusion', function () {
            // Step 1: Create a markdown document with code blocks
            $originalMarkdown = <<<'MARKDOWN'
---
title: "API Documentation"
description: "Complete API usage examples"
doctest_case_dir: examples/api
doctest_case_prefix: api_
doctest_min_lines: 2
doctest_included_types: ["php", "javascript"]
---

# API Documentation

## Authentication Example

```php id="auth_example"
$client = new ApiClient();
$client->authenticate('api-key-123');
$response = $client->getUser(456);
```

## JavaScript Usage

```javascript id="js_example"
const client = new ApiClient();
await client.authenticate('api-key-123');
const user = await client.getUser(456);
```

## Short Example (Should be excluded)

```php
echo "Short";
```

## Wrong Language (Should be excluded)

```python
print("Python code")
print("Should be ignored")
```
MARKDOWN;

            // Step 2: Parse with MarkdownFile and verify auto-generated IDs
            $markdownFile = MarkdownFile::fromString($originalMarkdown, '/docs/api.md');
            $allCodeBlocks = iterator_to_array($markdownFile->codeBlocks());
            
            expect($allCodeBlocks)->toHaveCount(4);
            
            // Verify auto-generated IDs for blocks without explicit IDs
            $shortBlock = $allCodeBlocks[2]; // The short PHP block
            $pythonBlock = $allCodeBlocks[3]; // The Python block
            expect($shortBlock->id)->toHaveLength(4); // 4-char hex ID
            expect($pythonBlock->id)->toHaveLength(4); // 4-char hex ID

            // Step 3: Create DoctestFile instances and verify filtering
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));
            
            expect($doctests)->toHaveCount(2); // Only PHP and JS blocks that meet criteria
            
            $phpDoctest = $doctests[0];
            $jsDoctest = $doctests[1];
            
            expect($phpDoctest->id)->toBe('auth_example');
            expect($phpDoctest->language)->toBe('php');
            expect($phpDoctest->linesOfCode)->toBeGreaterThanOrEqual(2);
            
            expect($jsDoctest->id)->toBe('js_example');
            expect($jsDoctest->language)->toBe('javascript');
            expect($jsDoctest->linesOfCode)->toBeGreaterThanOrEqual(2);

            // Step 4: Verify extracted file content generation
            $phpFileContent = $phpDoctest->toFileContent();
            expect($phpFileContent)->toContain('// @doctest id=auth_example');
            expect($phpFileContent)->toContain('$client = new ApiClient();');
            expect($phpFileContent)->toContain('$client->authenticate(\'api-key-123\');');
            
            $jsFileContent = $jsDoctest->toFileContent();
            expect($jsFileContent)->toContain('// @doctest id=js_example');
            expect($jsFileContent)->toContain('const client = new ApiClient();');
            expect($jsFileContent)->toContain('await client.authenticate(\'api-key-123\');');

            // Step 5: Simulate source modification with include metadata
            $modifiedMarkdown = $markdownFile->withReplacedCodeBlocks(function($codeBlock) use ($markdownFile) {
                // Create doctest to check if it should be extracted
                $markdownInfo = MarkdownInfo::from($markdownFile);
                
                $doctest = new DoctestFile(
                    id: $codeBlock->id,
                    language: $codeBlock->language,
                    linesOfCode: $codeBlock->linesOfCode,
                    code: $codeBlock->content,
                    codePath: $markdownInfo->caseDir . '/' . $markdownInfo->casePrefix . $codeBlock->id . '.' . \Cognesy\Utils\ProgrammingLanguage::fileExtension($codeBlock->language),
                    sourceMarkdown: $markdownInfo,
                );
                
                // Check if this block should be extracted
                if (empty($doctest->id) || 
                    !in_array($doctest->language, $doctest->sourceMarkdown->includedTypes)
                    || $doctest->linesOfCode < $doctest->sourceMarkdown->minLines
                ) {
                    return $codeBlock; // Keep unchanged
                }
                
                // Generate include path using the doctest's actual codePath
                $includePath = $doctest->codePath;
                
                // Add include metadata
                $updatedMetadata = array_merge($codeBlock->metadata, [
                    'include' => $includePath
                ]);
                
                return $codeBlock->withContent('// Code extracted - will be included from external file')
                    ->withMetadata($updatedMetadata);
            });

            // Step 6: Verify the modified markdown has correct include metadata
            $modifiedContent = $modifiedMarkdown->toString();
            
            expect($modifiedContent)->toContain('include="examples/api/api_auth_example.php"');
            expect($modifiedContent)->toContain('include="examples/api/api_js_example.js"');
            
            // Non-extracted blocks should remain unchanged
            expect($modifiedContent)->toContain('echo "Short"');
            expect($modifiedContent)->toContain('print("Python code")');
            
            // Extracted code should be replaced
            expect($modifiedContent)->not()->toContain('$client = new ApiClient();');
            expect($modifiedContent)->not()->toContain('const client = new ApiClient();');

            // Step 7: Verify GenerateDocs compatibility
            // Parse the modified markdown to ensure GenerateDocs can process includes
            $finalMarkdownFile = MarkdownFile::fromString($modifiedContent, '/docs/api.md');
            $finalCodeBlocks = iterator_to_array($finalMarkdownFile->codeBlocks());
            
            $phpBlock = null;
            $jsBlock = null;
            
            foreach ($finalCodeBlocks as $block) {
                if ($block->language === 'php' && $block->hasMetadata('include')) {
                    $phpBlock = $block;
                }
                if ($block->language === 'javascript' && $block->hasMetadata('include')) {
                    $jsBlock = $block;
                }
            }
            
            expect($phpBlock)->not()->toBeNull();
            expect($jsBlock)->not()->toBeNull();
            expect($phpBlock->metadata('include'))->toBe('examples/api/api_auth_example.php');
            expect($jsBlock->metadata('include'))->toBe('examples/api/api_js_example.js');
        });

        it('handles complex real-world documentation scenario', function () {
            // Real-world scenario with multiple code blocks, mixed languages, and edge cases
            $complexMarkdown = <<<'MARKDOWN'
---
title: "HTTP Client Documentation"
description: "Complete HTTP client usage guide"
doctest_case_dir: examples/http
doctest_case_prefix: http_
doctest_min_lines: 3
doctest_included_types: ["php"]
---

# HTTP Client Documentation

## Basic Usage

```php id="basic_usage"
use HttpClient\Client;

$client = new Client();
$response = $client->get('https://api.example.com/users');
$users = $response->json();
```

## Error Handling

```php
// @doctest id="error_handling"
try {
    $response = $client->get('https://api.example.com/invalid');
    $data = $response->json();
} catch (HttpException $e) {
    error_log('HTTP Error: ' . $e->getMessage());
    throw $e;
}
```

## Configuration (Too short - should be excluded)

```php
$config = ['timeout' => 30];
```

## Curl Example (Wrong language - should be excluded)

```bash
curl -X GET https://api.example.com/users
```

## Advanced Usage with Custom Headers

```php id="advanced_usage"
$headers = [
    'Authorization' => 'Bearer token123',
    'Content-Type' => 'application/json',
    'User-Agent' => 'MyApp/1.0'
];

$client = new Client();
$response = $client->post('https://api.example.com/data', [
    'headers' => $headers,
    'json' => ['name' => 'John', 'email' => 'john@example.com']
]);
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($complexMarkdown, '/docs/http-client.md');
            
            // Verify parsing and auto ID generation
            $allCodeBlocks = iterator_to_array($markdownFile->codeBlocks());
            expect($allCodeBlocks)->toHaveCount(5);
            
            // Create doctests and verify filtering
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));
            expect($doctests)->toHaveCount(3); // Only the 3 PHP blocks that meet min_lines criteria
            
            // Verify the correct blocks were selected
            $doctestIds = array_map(fn($d) => $d->id, $doctests);
            expect($doctestIds)->toContain('basic_usage');
            expect($doctestIds)->toContain('error_handling');
            expect($doctestIds)->toContain('advanced_usage');
            
            // Verify file content generation includes proper formatting
            foreach ($doctests as $doctest) {
                $fileContent = $doctest->toFileContent();
                //expect($fileContent)->toStartWith('<?php');
                expect($fileContent)->toContain('// @doctest id=');
            }
            
            // Verify specific content in the basic_usage block
            $basicUsageDoctest = array_values(array_filter($doctests, fn($d) => $d->id === 'basic_usage'))[0];
            expect($basicUsageDoctest->toFileContent())->toContain('use HttpClient\Client;');
            
            // Verify that when processed for includes, the right blocks are modified
            $modifiedForIncludes = $markdownFile->withReplacedCodeBlocks(function($codeBlock) use ($markdownFile) {
                $caseDir = DoctestFile::getEffectiveCaseDir($markdownFile);
                $casePrefix = DoctestFile::getEffectiveCasePrefix($markdownFile);
                
                $markdownInfo = MarkdownInfo::from($markdownFile);
                
                $doctest = new DoctestFile(
                    id: $codeBlock->id,
                    language: $codeBlock->language,  
                    linesOfCode: $codeBlock->linesOfCode,
                    code: $codeBlock->content,
                    codePath: $markdownInfo->caseDir . '/' . $markdownInfo->casePrefix . $codeBlock->id . '.' . \Cognesy\Utils\ProgrammingLanguage::fileExtension($codeBlock->language),
                    sourceMarkdown: $markdownInfo,
                );
                
                if (empty($doctest->id) || 
                    !in_array($doctest->language, $doctest->sourceMarkdown->includedTypes) || 
                    $doctest->linesOfCode < $doctest->sourceMarkdown->minLines) {
                    return $codeBlock;
                }
                
                $includePath = $doctest->codePath;
                
                $updatedMetadata = array_merge($codeBlock->metadata, ['include' => $includePath]);
                return $codeBlock->withContent('// Extracted code')->withMetadata($updatedMetadata);
            });
            
            $finalContent = $modifiedForIncludes->toString();
            
            // Verify includes were added for extractable blocks
            expect($finalContent)->toContain('include="examples/http/http_basic_usage.php"');
            expect($finalContent)->toContain('include="examples/http/http_error_handling.php"');
            expect($finalContent)->toContain('include="examples/http/http_advanced_usage.php"');
            
            // Verify non-extractable blocks remain unchanged
            expect($finalContent)->toContain('$config = [\'timeout\' => 30];');
            expect($finalContent)->toContain('curl -X GET https://api.example.com/users');
        });
    });

    describe('round-trip compatibility', function () {
        it('maintains data integrity through complete workflow', function () {
            $originalMarkdown = <<<'MARKDOWN'
---
title: "Round Trip Test"
doctest_case_dir: examples/roundtrip
doctest_case_prefix: rt_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="original" title="Original Code" description="Test code"
echo "Original content";
```
MARKDOWN;

            // Parse original
            $originalFile = MarkdownFile::fromString($originalMarkdown, '/test.md');
            $originalBlocks = iterator_to_array($originalFile->codeBlocks());
            
            // Create doctest
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($originalFile));
            expect($doctests)->toHaveCount(1);
            
            // Generate extracted file content
            $extractedContent = $doctests[0]->toFileContent();
            
            // Modify source with include
            $modifiedFile = $originalFile->withReplacedCodeBlocks(function($block) {
                $metadata = array_merge($block->metadata, [
                    'include' => 'examples/roundtrip/rt_original.php'
                ]);
                return $block->withContent('// Extracted')->withMetadata($metadata);
            });
            
            // Simulate GenerateDocs processing (include resolution)
            $finalFile = $modifiedFile->withReplacedCodeBlocks(function($block) use ($extractedContent) {
                if ($block->hasMetadata('include')) {
                    // Simulate loading from external file
                    $cleanContent = str_replace(['<?php', '// @doctest id=original'], '', $extractedContent);
                    $cleanContent = trim($cleanContent);
                    return $block->withContent($cleanContent);
                }
                return $block;
            });
            
            // Verify final content matches original semantic content
            $finalBlocks = iterator_to_array($finalFile->codeBlocks());
            expect($finalBlocks)->toHaveCount(1);
            
            $finalBlock = $finalBlocks[0];
            expect($finalBlock->content)->toContain('echo "Original content"');
            expect($finalBlock->metadata('title'))->toBe('Original Code');
            expect($finalBlock->metadata('description'))->toBe('Test code');
        });
    });
});