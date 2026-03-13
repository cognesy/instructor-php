<?php declare(strict_types=1);

use Cognesy\Doctools\Quality\Services\RulesLoader;
use Cognesy\Utils\Files;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/doctools_quality_loader_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

function writeRuleFile(string $path, string $contents): void {
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    file_put_contents($path, $contents);
}

it('loads regex and ast-grep rules from YAML', function () {
    $rulesPath = $this->tempDir . '/rules.yaml';
    writeRuleFile($rulesPath, <<<'YAML'
version: 1
rules:
  - id: quality.no_from_config
    engine: regex
    scope: markdown
    pattern: '/\bStructuredOutput::fromConfig\s*\(/'
    message: 'No fromConfig'
  - id: quality.no_request
    engine: ast-grep
    scope: php-snippet
    language: php
    pattern: '$OBJ->request($$$ARGS)'
    message: 'No request'
YAML);

    $loaded = (new RulesLoader())->load($rulesPath);

    expect($loaded->rules)->toHaveCount(2);
    expect($loaded->rules[0]->id)->toBe('quality.no_from_config');
    expect($loaded->rules[1]->language)->toBe('php');
});

it('fails when ast-grep rule has no language', function () {
    $rulesPath = $this->tempDir . '/rules.yaml';
    writeRuleFile($rulesPath, <<<'YAML'
version: 1
rules:
  - id: quality.no_request
    engine: ast-grep
    scope: php-snippet
    pattern: '$OBJ->request($$$ARGS)'
    message: 'No request'
YAML);

    expect(fn() => (new RulesLoader())->load($rulesPath))
        ->toThrow(InvalidArgumentException::class, 'requires `language`');
});

