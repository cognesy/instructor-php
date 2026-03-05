<?php declare(strict_types=1);

use Cognesy\Config\BasePath;
use Cognesy\Config\Config;
use Cognesy\Config\Dsn;

function dsnStringFromExample(string $path): string {
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Failed to read example: {$path}");
    }

    $matches = [];
    $matchedLiteral = preg_match('/(?:with|from)Dsn\((["\'])(.+?)\\1\)/', $content, $matches);
    if ($matchedLiteral === 1) {
        return $matches[2];
    }

    $matchedVariable = preg_match('/\$dsn\s*=\s*(["\'])(.+?)\\1/s', $content, $matches);
    if ($matchedVariable === 1) {
        return $matches[2];
    }

    throw new RuntimeException("Missing DSN string in example: {$path}");
}

function xaiModelFromConfig(): string {
    $entry = Config::fromPaths(BasePath::resolve('packages/polyglot/resources/config/llm/presets'))
        ->load('xai.yaml');
    $model = $entry->toArray()['model'] ?? '';
    if ($model === '') {
        throw new RuntimeException('xai model is not configured');
    }
    return $model;
}

it('keeps instructor DSN example aligned with xai model config', function () {
    $model = xaiModelFromConfig();
    $dsn = Dsn::fromString(dsnStringFromExample(BasePath::resolve('examples/A02_Advanced/DSN/run.php')));

    expect($dsn->param('driver'))->toBe('xai');
    expect($dsn->param('model'))->toBe($model);
});

it('keeps polyglot DSN example aligned with xai model config', function () {
    $model = xaiModelFromConfig();
    $dsn = Dsn::fromString(dsnStringFromExample(BasePath::resolve('examples/B02_LLMAdvanced/DSN/run.php')));

    expect($dsn->param('driver'))->toBe('xai');
    expect($dsn->param('model'))->toBe($model);
});
