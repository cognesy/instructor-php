<?php declare(strict_types=1);

use Cognesy\Config\BasePath;
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Config\HttpClientConfig;

it('discovers bundled HTTP presets when consumer app has no local config', function () {
    $consumerRoot = httpPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        $config = HttpClientConfig::fromPreset('curl');

        expect($config->driver)->toBe('curl')
            ->and($config->connectTimeout)->toBe(5)
            ->and($config->requestTimeout)->toBe(60);
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

it('discovers bundled debug presets when consumer app has no local config', function () {
    $consumerRoot = httpPresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        $config = DebugConfig::fromPreset('off');

        expect($config->httpEnabled)->toBeFalse()
            ->and($config->httpTrace)->toBeFalse()
            ->and($config->httpResponseStream)->toBeFalse();
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

function httpPresetDiscoveryConsumerRoot(): string {
    $dir = sys_get_temp_dir() . '/instructor-http-preset-discovery-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/composer.json', "{}\n");

    register_shutdown_function(static function () use ($dir): void {
        httpPresetDiscoveryDeleteDir($dir);
    });

    return $dir;
}

function httpPresetDiscoveryDeleteDir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            httpPresetDiscoveryDeleteDir($path);
            continue;
        }

        unlink($path);
    }

    rmdir($dir);
}
