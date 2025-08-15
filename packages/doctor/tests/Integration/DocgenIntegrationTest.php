<?php

use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\MintlifyDocumentation;
use Cognesy\Doctor\Docgen\MkDocsDocumentation;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExampleGroup;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Files;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/docgen_integration_test_' . uniqid();
    $this->setupTestEnvironment();
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

function setupTestEnvironment(): void {
    $tempDir = test()->tempDir;
    
    // Create directory structure
    $dirs = [
        'docs-source',
        'mintlify-target',
        'mkdocs-target', 
        'cookbook',
        'packages/instructor/docs',
        'packages/polyglot/docs',
        'packages/http-client/docs',
        'examples/basics',
        'examples/advanced'
    ];
    
    foreach ($dirs as $dir) {
        mkdir("$tempDir/$dir", 0755, true);
    }
    
    // Create source documentation files
    file_put_contents("$tempDir/docs-source/index.md", "# Main Documentation\n\nWelcome to the docs.");
    file_put_contents("$tempDir/docs-source/getting-started.md", "# Getting Started\n\nStart here.");
    
    // Create Mintlify config
    $mintlifyConfig = [
        'name' => 'Test Documentation',
        'logo' => '/logo.png',
        'navigation' => [
            ['Home' => 'index'],
            ['Getting Started' => 'getting-started']
        ]
    ];
    file_put_contents("$tempDir/docs-source/mint.json", json_encode($mintlifyConfig, JSON_PRETTY_PRINT));
    
    // Create MkDocs config  
    $mkdocsConfig = "site_name: Test Documentation\nnav:\n  - Home: index.md\n  - Getting Started: getting-started.md";
    file_put_contents("$tempDir/docs-source/mkdocs.yml", $mkdocsConfig);
    
    // Create package documentation
    file_put_contents("$tempDir/packages/instructor/docs/overview.md", "# Instructor Package\n\nOverview content.");
    file_put_contents("$tempDir/packages/instructor/docs/api.md", "# API Reference\n\nAPI documentation.");
    
    file_put_contents("$tempDir/packages/polyglot/docs/overview.md", "# Polyglot Package\n\nPolyglot content.");
    file_put_contents("$tempDir/packages/http-client/docs/overview.md", "# HTTP Client\n\nHTTP client docs.");
    
    // Create example files
    file_put_contents("$tempDir/examples/basics/simple.php", "<?php\n// Simple example\necho 'Hello World';");
    file_put_contents("$tempDir/examples/advanced/complex.php", "<?php\n// Complex example\nclass Example {}");
}

describe('Documentation Generation Integration', function () {
    test('both generators can work with same source structure', function () {
        $examples = $this->createMock(ExampleRepository::class);
        
        // Create test examples
        $basicExample = new Example(
            name: 'SimpleExample',
            runPath: $this->tempDir . '/examples/basics/simple.php',
            group: 'A01_Basics',
            tab: 'basics',
            title: 'Simple Example',
            hasTitle: true
        );
        
        $advancedExample = new Example(
            name: 'ComplexExample', 
            runPath: $this->tempDir . '/examples/advanced/complex.php',
            group: 'A02_Advanced',
            tab: 'advanced',
            title: 'Complex Example',
            hasTitle: true
        );
        
        $basicGroup = new ExampleGroup('Basics', [$basicExample]);
        $advancedGroup = new ExampleGroup('Advanced', [$advancedExample]);
        
        $examples->method('getExampleGroups')->willReturn([$basicGroup, $advancedGroup]);
        
        // Configure Mintlify
        $mintlifyConfig = DocumentationConfig::create(
            docsSourceDir: $this->tempDir . '/docs-source',
            docsTargetDir: $this->tempDir . '/mintlify-target',
            cookbookTargetDir: $this->tempDir . '/cookbook',
            mintlifySourceIndexFile: $this->tempDir . '/docs-source/mint.json',
            mintlifyTargetIndexFile: $this->tempDir . '/mintlify-target/mint.json',
            codeblocksDir: '',
            dynamicGroups: ['Basics', 'Advanced']
        );
        
        // Configure MkDocs
        $mkdocsConfig = DocumentationConfig::create(
            docsSourceDir: $this->tempDir . '/docs-source',
            docsTargetDir: $this->tempDir . '/mkdocs-target',
            cookbookTargetDir: $this->tempDir . '/cookbook',
            mintlifySourceIndexFile: '',
            mintlifyTargetIndexFile: '',
            codeblocksDir: '',
            dynamicGroups: ['Basics', 'Advanced']
        );
        
        // Generate with both systems
        $mintlify = new MintlifyDocumentation($examples, $mintlifyConfig);
        $mkdocs = new MkDocsDocumentation($examples, $mkdocsConfig);
        
        $mintlifyResult = $mintlify->generateAll();
        $mkdocsResult = $mkdocs->generateAll();
        
        // Both should succeed
        expect($mintlifyResult->isSuccess())->toBeTrue();
        expect($mkdocsResult->isSuccess())->toBeTrue();
        
        // Check Mintlify output
        expect(is_dir($this->tempDir . '/mintlify-target'))->toBeTrue();
        expect(file_exists($this->tempDir . '/mintlify-target/index.mdx'))->toBeTrue();
        expect(file_exists($this->tempDir . '/mintlify-target/mint.json'))->toBeTrue();
        expect(file_exists($this->tempDir . '/cookbook/basics/SimpleExample.mdx'))->toBeTrue();
        expect(file_exists($this->tempDir . '/cookbook/advanced/ComplexExample.mdx'))->toBeTrue();
        
        // Check MkDocs output
        expect(is_dir($this->tempDir . '/mkdocs-target'))->toBeTrue();
        expect(file_exists($this->tempDir . '/mkdocs-target/index.md'))->toBeTrue();
        expect(file_exists($this->tempDir . '/mkdocs-target/mkdocs.yml'))->toBeTrue();
        expect(file_exists($this->tempDir . '/cookbook/basics/SimpleExample.md'))->toBeTrue();
        expect(file_exists($this->tempDir . '/cookbook/advanced/ComplexExample.md'))->toBeTrue();
    });
    
    test('file extensions are handled correctly', function () {
        $examples = $this->createMock(ExampleRepository::class);
        $examples->method('getExampleGroups')->willReturn([]);
        
        $mintlifyConfig = DocumentationConfig::create(
            docsSourceDir: $this->tempDir . '/docs-source',
            docsTargetDir: $this->tempDir . '/mintlify-target',
            cookbookTargetDir: $this->tempDir . '/cookbook',
            mintlifySourceIndexFile: $this->tempDir . '/docs-source/mint.json',
            mintlifyTargetIndexFile: $this->tempDir . '/mintlify-target/mint.json',
            codeblocksDir: '',
            dynamicGroups: []
        );
        
        $mkdocsConfig = DocumentationConfig::create(
            docsSourceDir: $this->tempDir . '/docs-source',
            docsTargetDir: $this->tempDir . '/mkdocs-target',
            cookbookTargetDir: $this->tempDir . '/cookbook',
            mintlifySourceIndexFile: '',
            mintlifyTargetIndexFile: '',
            codeblocksDir: '',
            dynamicGroups: []
        );
        
        $mintlify = new MintlifyDocumentation($examples, $mintlifyConfig);
        $mkdocs = new MkDocsDocumentation($examples, $mkdocsConfig);
        
        $mintlify->initializeBaseFiles();
        $mkdocs->initializeBaseFiles();
        
        // Mintlify should create .mdx files
        expect(file_exists($this->tempDir . '/mintlify-target/index.mdx'))->toBeTrue();
        expect(file_exists($this->tempDir . '/mintlify-target/getting-started.mdx'))->toBeTrue();
        
        // MkDocs should keep .md files
        expect(file_exists($this->tempDir . '/mkdocs-target/index.md'))->toBeTrue();
        expect(file_exists($this->tempDir . '/mkdocs-target/getting-started.md'))->toBeTrue();
    });
    
    test('concurrent generation works without conflicts', function () {
        $examples = $this->createMock(ExampleRepository::class);
        
        $example = new Example(
            name: 'TestExample',
            runPath: $this->tempDir . '/examples/basics/simple.php',
            group: 'A01_Test',
            tab: 'test',
            title: 'Test Example',
            hasTitle: true
        );
        
        $group = new ExampleGroup('Test', [$example]);
        $examples->method('getExampleGroups')->willReturn([$group]);
        
        $mintlifyConfig = DocumentationConfig::create(
            docsSourceDir: $this->tempDir . '/docs-source',
            docsTargetDir: $this->tempDir . '/mintlify-target',
            cookbookTargetDir: $this->tempDir . '/cookbook',
            mintlifySourceIndexFile: $this->tempDir . '/docs-source/mint.json',
            mintlifyTargetIndexFile: $this->tempDir . '/mintlify-target/mint.json',
            codeblocksDir: '',
            dynamicGroups: ['Test']
        );
        
        $mkdocsConfig = DocumentationConfig::create(
            docsSourceDir: $this->tempDir . '/docs-source',
            docsTargetDir: $this->tempDir . '/mkdocs-target',
            cookbookTargetDir: $this->tempDir . '/cookbook',
            mintlifySourceIndexFile: '',
            mintlifyTargetIndexFile: '',
            codeblocksDir: '',
            dynamicGroups: ['Test']
        );
        
        $mintlify = new MintlifyDocumentation($examples, $mintlifyConfig);
        $mkdocs = new MkDocsDocumentation($examples, $mkdocsConfig);
        
        // Generate simultaneously (in sequence for test)
        $mintlifyResult = $mintlify->generateAll();
        $mkdocsResult = $mkdocs->generateAll();
        
        expect($mintlifyResult->isSuccess())->toBeTrue();
        expect($mkdocsResult->isSuccess())->toBeTrue();
        
        // Both should have processed the same example
        expect($mintlifyResult->filesProcessed)->toBeGreaterThan(0);
        expect($mkdocsResult->filesProcessed)->toBeGreaterThan(0);
        
        // Files should exist for both formats
        expect(file_exists($this->tempDir . '/cookbook/test/TestExample.mdx'))->toBeTrue();
        expect(file_exists($this->tempDir . '/cookbook/test/TestExample.md'))->toBeTrue();
    });
    
    test('clearing one generator does not affect the other', function () {
        $examples = $this->createMock(ExampleRepository::class);
        $examples->method('getExampleGroups')->willReturn([]);
        
        $mintlifyConfig = DocumentationConfig::create(
            docsSourceDir: $this->tempDir . '/docs-source',
            docsTargetDir: $this->tempDir . '/mintlify-target',
            cookbookTargetDir: $this->tempDir . '/cookbook',
            mintlifySourceIndexFile: $this->tempDir . '/docs-source/mint.json',
            mintlifyTargetIndexFile: $this->tempDir . '/mintlify-target/mint.json',
            codeblocksDir: '',
            dynamicGroups: []
        );
        
        $mkdocsConfig = DocumentationConfig::create(
            docsSourceDir: $this->tempDir . '/docs-source',
            docsTargetDir: $this->tempDir . '/mkdocs-target',
            cookbookTargetDir: $this->tempDir . '/cookbook',
            mintlifySourceIndexFile: '',
            mintlifyTargetIndexFile: '',
            codeblocksDir: '',
            dynamicGroups: []
        );
        
        $mintlify = new MintlifyDocumentation($examples, $mintlifyConfig);
        $mkdocs = new MkDocsDocumentation($examples, $mkdocsConfig);
        
        // Generate both
        $mintlify->generateAll();
        $mkdocs->generateAll();
        
        expect(is_dir($this->tempDir . '/mintlify-target'))->toBeTrue();
        expect(is_dir($this->tempDir . '/mkdocs-target'))->toBeTrue();
        
        // Clear only Mintlify
        $mintlifyResult = $mintlify->clearDocumentation();
        
        expect($mintlifyResult->isSuccess())->toBeTrue();
        expect(is_dir($this->tempDir . '/mintlify-target'))->toBeFalse();
        expect(is_dir($this->tempDir . '/mkdocs-target'))->toBeTrue(); // Should still exist
        
        // Clear MkDocs
        $mkdocsResult = $mkdocs->clearDocumentation();
        
        expect($mkdocsResult->isSuccess())->toBeTrue();
        expect(is_dir($this->tempDir . '/mkdocs-target'))->toBeFalse();
    });
});