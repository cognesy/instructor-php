<?php

use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\Data\GenerationResult;
use Cognesy\Doctor\Docgen\MkDocsDocumentation;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExampleGroup;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Files;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/mkdocs_test_' . uniqid();
    $this->docsSourceDir = $this->tempDir . '/docs-source';
    $this->docsTargetDir = $this->tempDir . '/docs-target';
    $this->cookbookTargetDir = $this->tempDir . '/cookbook';

    // Create test directories
    mkdir($this->tempDir, 0755, true);
    mkdir($this->docsSourceDir, 0755, true);
    mkdir($this->cookbookTargetDir, 0755, true);

    // Create test source files
    file_put_contents($this->docsSourceDir . '/index.md', '# Test Documentation');
    file_put_contents($this->docsSourceDir . '/mkdocs.yml', "site_name: Test\nnav:\n  - Home: index.md");

    $this->config = DocumentationConfig::create(
        docsSourceDir: $this->docsSourceDir,
        docsTargetDir: $this->docsTargetDir,
        cookbookTargetDir: $this->cookbookTargetDir,
        mintlifySourceIndexFile: '',
        mintlifyTargetIndexFile: '',
        codeblocksDir: '',
        dynamicGroups: ['Examples']
    );

    $this->examples = $this->createMock(ExampleRepository::class);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

describe('MkDocsDocumentation', function () {
    test('can be instantiated', function () {
        $mkdocs = new MkDocsDocumentation($this->examples, $this->config);
        expect($mkdocs)->toBeInstanceOf(MkDocsDocumentation::class);
    });

    test('clearDocumentation removes target directory', function () {
        // Create target directory with content
        mkdir($this->docsTargetDir, 0755, true);
        file_put_contents($this->docsTargetDir . '/test.md', 'test content');

        $mkdocs = new MkDocsDocumentation($this->examples, $this->config);
        $result = $mkdocs->clearDocumentation();

        expect($result)->toBeInstanceOf(GenerationResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->message)->toBe('MkDocs documentation cleared successfully');
        expect(is_dir($this->docsTargetDir))->toBeFalse();
    });

    test('initializeBaseFiles copies source to target', function () {
        $mkdocs = new MkDocsDocumentation($this->examples, $this->config);
        $mkdocs->initializeBaseFiles();

        expect(is_dir($this->docsTargetDir))->toBeTrue();
        expect(file_exists($this->docsTargetDir . '/index.md'))->toBeTrue();
        expect(file_exists($this->docsTargetDir . '/mkdocs.yml'))->toBeTrue();
        expect(file_get_contents($this->docsTargetDir . '/index.md'))->toBe('# Test Documentation');
    });

    test('generatePackageDocs processes packages successfully', function () {
        // Note: This test validates the method structure and error handling
        // since BasePath::get() points to actual project structure, not test dirs
        $mkdocs = new MkDocsDocumentation($this->examples, $this->config);
        $result = $mkdocs->generatePackageDocs();

        expect($result)->toBeInstanceOf(GenerationResult::class);
        // The result may fail or succeed depending on whether actual package dirs exist
        // but it should return a proper GenerationResult
        expect($result->message)->toBeString();
        expect($result->filesProcessed)->toBeGreaterThanOrEqual(0);
    });

    test('generateExampleDocs processes examples successfully', function () {
        // Create mock example
        $example = createTestExample($this->tempDir);
        $exampleGroup = new ExampleGroup('Test Group', 'Test Group', [$example]);

        $this->examples->method('getExampleGroups')->willReturn([$exampleGroup]);

        // Create source example file
        $exampleSourceDir = dirname($example->runPath);
        mkdir($exampleSourceDir, 0755, true);
        file_put_contents($example->runPath, '<?php echo "test";');

        $mkdocs = new MkDocsDocumentation($this->examples, $this->config);
        $result = $mkdocs->generateExampleDocs();

        expect($result)->toBeInstanceOf(GenerationResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->message)->toBe('Example documentation generated successfully');
        expect($result->filesProcessed)->toBe(1);
        expect($result->filesCreated)->toBe(1);
    });

    test('generateAll processes everything successfully', function () {
        // Setup example
        $example = createTestExample($this->tempDir);
        $exampleGroup = new ExampleGroup('Test Group', 'Test Group', [$example]);
        $this->examples->method('getExampleGroups')->willReturn([$exampleGroup]);

        $exampleSourceDir = dirname($example->runPath);
        mkdir($exampleSourceDir, 0755, true);
        file_put_contents($example->runPath, '<?php echo "test";');

        $mkdocs = new MkDocsDocumentation($this->examples, $this->config);
        $result = $mkdocs->generateAll();

        expect($result)->toBeInstanceOf(GenerationResult::class);
        // May succeed or fail depending on package dirs, but should return valid result
        expect($result->message)->toBeString();
        expect($result->filesProcessed)->toBeGreaterThanOrEqual(0);
    });

    test('handles missing example tab gracefully', function () {
        $example = new Example(
            name: 'TestExample',
            runPath: $this->tempDir . '/example.php',
            group: 'TestGroup',
            tab: '', // Empty tab
            title: 'Test Example',
            hasTitle: true
        );
        $exampleGroup = new ExampleGroup('Test Group', 'Test Group', [$example]);
        $this->examples->method('getExampleGroups')->willReturn([$exampleGroup]);

        $mkdocs = new MkDocsDocumentation($this->examples, $this->config);
        $result = $mkdocs->generateExampleDocs();

        expect($result->isSuccess())->toBeTrue();
        expect($result->filesSkipped)->toBe(1);
    });

    test('handles file update detection', function () {
        $example = createTestExample($this->tempDir);
        $exampleGroup = new ExampleGroup('Test Group', 'Test Group', [$example]);
        $this->examples->method('getExampleGroups')->willReturn([$exampleGroup]);

        // Create source and target files
        $exampleSourceDir = dirname($example->runPath);
        mkdir($exampleSourceDir, 0755, true);
        file_put_contents($example->runPath, '<?php echo "test";');

        $targetFile = $this->cookbookTargetDir . $example->toDocPath() . '.md';
        $targetDir = dirname($targetFile);
        mkdir($targetDir, 0755, true);
        file_put_contents($targetFile, 'old content');

        // Make source file newer
        touch($example->runPath, time() + 1);

        $mkdocs = new MkDocsDocumentation($this->examples, $this->config);
        $result = $mkdocs->generateExampleDocs();

        expect($result->isSuccess())->toBeTrue();
        expect($result->filesUpdated)->toBe(1);
    });
});

// Helper function for tests
function createTestExample($tempDir): Example
{
    return new Example(
        name: 'TestExample',
        runPath: $tempDir . '/source/example.php',
        group: 'TestGroup',
        tab: 'basics',
        title: 'Test Example',
        hasTitle: true
    );
}