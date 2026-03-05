<?php declare(strict_types=1);

namespace Cognesy\Doctools\Tests\Feature\Quality;

use Cognesy\Doctools\Quality\Commands\RunDocsQualityCommand;
use Cognesy\Utils\Files;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/doctools_qa_command_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

function writeDocsQualityFile(string $path, string $contents): void {
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    file_put_contents($path, $contents);
}

it('reports anti-patterns, broken links, and snippet lint failures', function () {
    $docsDir = $this->tempDir . '/docs';
    writeDocsQualityFile($docsDir . '/index.md', <<<'MD'
See [broken](./missing-page).

StructuredOutput::fromConfig([]);

```php
echo ;
```
MD);

    $tester = new CommandTester(new RunDocsQualityCommand());
    $exitCode = $tester->execute([
        '--source-dir' => $docsDir,
        '--profile' => 'instructor',
    ], ['decorated' => false]);

    $display = $tester->getDisplay();
    expect($exitCode)->toBe(1);
    expect($display)->toContain('docs-qa: failed');
    expect($display)->toContain('StructuredOutput::fromConfig(');
    expect($display)->toContain('broken local link `./missing-page`');
    expect($display)->toContain('php snippet lint failed');
});

it('passes with valid docs and skips explicitly marked snippets', function () {
    $docsDir = $this->tempDir . '/docs';
    writeDocsQualityFile($docsDir . '/existing.md', '# Existing');
    writeDocsQualityFile($docsDir . '/index.md', <<<'MD'
See [ok](./existing.md).

```php
echo "ok";
```

```php
// qa:skip
echo ;
```
MD);

    $tester = new CommandTester(new RunDocsQualityCommand());
    $exitCode = $tester->execute([
        '--source-dir' => $docsDir,
        '--profile' => 'instructor',
    ], ['decorated' => false]);

    $display = $tester->getDisplay();
    expect($exitCode)->toBe(0);
    expect($display)->toContain('docs-qa: passed');
    expect($display)->toContain('1 snippet(s) checked, 1 skipped');
});

it('supports explicit YAML rules and json output', function () {
    $docsDir = $this->tempDir . '/docs';
    writeDocsQualityFile($docsDir . '/index.md', <<<'MD'
```php
$client->request('x');
```
MD);
    $rulesPath = $this->tempDir . '/rules.yaml';
    writeDocsQualityFile($rulesPath, <<<'YAML'
version: 1
rules:
  - id: test.no_request
    engine: ast-grep
    scope: php-snippet
    language: php
    pattern: '$OBJ->request($$$ARGS)'
    message: 'Do not use request directly.'
YAML);

    $tester = new CommandTester(new RunDocsQualityCommand());
    $exitCode = $tester->execute([
        '--source-dir' => $docsDir,
        '--profile' => 'none',
        '--rules' => $rulesPath,
        '--format' => 'json',
    ], ['decorated' => false]);

    $display = $tester->getDisplay();
    expect($exitCode)->toBe(1);
    expect($display)->toContain('"status": "failed"');
    expect($display)->toContain('test.no_request');
});

it('fails in strict mode when ast-grep is missing and ast-grep rules are configured', function () {
    $docsDir = $this->tempDir . '/docs';
    writeDocsQualityFile($docsDir . '/index.md', <<<'MD'
```php
$client->request('x');
```
MD);
    $rulesPath = $this->tempDir . '/rules.yaml';
    writeDocsQualityFile($rulesPath, <<<'YAML'
version: 1
rules:
  - id: test.no_request
    engine: ast-grep
    scope: php-snippet
    language: php
    pattern: '$OBJ->request($$$ARGS)'
    message: 'Do not use request directly.'
YAML);

    $tester = new CommandTester(new RunDocsQualityCommand());
    $exitCode = $tester->execute([
        '--source-dir' => $docsDir,
        '--profile' => 'none',
        '--rules' => $rulesPath,
        '--ast-grep-bin' => '/path/that/does/not/exist',
    ], ['decorated' => false]);

    $display = $tester->getDisplay();
    expect($exitCode)->toBe(1);
    expect($display)->toContain('ast-grep binary not available');
});

it('continues when ast-grep is missing and strict mode is disabled', function () {
    $docsDir = $this->tempDir . '/docs';
    writeDocsQualityFile($docsDir . '/index.md', <<<'MD'
```php
$client->request('x');
```
MD);
    $rulesPath = $this->tempDir . '/rules.yaml';
    writeDocsQualityFile($rulesPath, <<<'YAML'
version: 1
rules:
  - id: test.no_request
    engine: ast-grep
    scope: php-snippet
    language: php
    pattern: '$OBJ->request($$$ARGS)'
    message: 'Do not use request directly.'
YAML);

    $tester = new CommandTester(new RunDocsQualityCommand());
    $exitCode = $tester->execute([
        '--source-dir' => $docsDir,
        '--profile' => 'none',
        '--rules' => $rulesPath,
        '--ast-grep-bin' => '/path/that/does/not/exist',
        '--no-strict' => true,
    ], ['decorated' => false]);

    $display = $tester->getDisplay();
    expect($exitCode)->toBe(0);
    expect($display)->toContain('docs-qa: passed');
});

it('loads package-local rules from docs root .qa/rules.yaml', function () {
    $docsDir = $this->tempDir . '/docs';
    writeDocsQualityFile($docsDir . '/.qa/rules.yaml', <<<'YAML'
version: 1
rules:
  - id: local.no_legacy_token
    engine: regex
    scope: markdown
    pattern: '/\blegacyToken\b/'
    message: 'legacyToken must not appear in docs.'
YAML);
    writeDocsQualityFile($docsDir . '/index.md', "Use legacyToken value.\n");

    $tester = new CommandTester(new RunDocsQualityCommand());
    $exitCode = $tester->execute([
        '--source-dir' => $docsDir,
        '--profile' => 'none',
    ], ['decorated' => false]);

    $display = $tester->getDisplay();
    expect($exitCode)->toBe(1);
    expect($display)->toContain('local.no_legacy_token');
});
