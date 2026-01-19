<?php declare(strict_types=1);

use Cognesy\Config\BasePath;
use Cognesy\Config\ConfigPresets;
use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Dsn;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

function dsnStringFromExample(string $path): string {
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Failed to read example: {$path}");
    }

    $matches = [];
    $matched = preg_match('/withDsn\((["\'])(.+?)\\1\)/', $content, $matches);
    if ($matched !== 1) {
        throw new RuntimeException("Missing withDsn() call in example: {$path}");
    }

    return $matches[2];
}

function xaiModelFromConfig(): string {
    $presets = ConfigPresets::using(ConfigResolver::default())->for(LLMConfig::group());
    $config = $presets->get('xai');
    $model = $config['model'] ?? '';
    if ($model === '') {
        throw new RuntimeException('xai model is not configured');
    }
    return $model;
}

it('keeps instructor DSN example aligned with xai model preset', function () {
    $model = xaiModelFromConfig();
    $dsn = Dsn::fromString(dsnStringFromExample(BasePath::get('examples/A02_Advanced/DSN/run.php')));

    expect($dsn->param('preset'))->toBe('xai');
    expect($dsn->param('model'))->toBe($model);
});

it('keeps polyglot DSN example aligned with xai model preset', function () {
    $model = xaiModelFromConfig();
    $dsn = Dsn::fromString(dsnStringFromExample(BasePath::get('examples/B02_LLMAdvanced/DSN/run.php')));

    expect($dsn->param('preset'))->toBe('xai');
    expect($dsn->param('model'))->toBe($model);
});
