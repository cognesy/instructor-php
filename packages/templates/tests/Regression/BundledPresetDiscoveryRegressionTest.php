<?php declare(strict_types=1);

use Cognesy\Config\BasePath;
use Cognesy\Template\Config\TemplateEngineConfig;
use Cognesy\Template\Enums\TemplateEngineType;

it('discovers bundled template presets when consumer app has no local config', function () {
    $consumerRoot = templatePresetDiscoveryConsumerRoot();
    BasePath::set($consumerRoot);

    try {
        $config = TemplateEngineConfig::fromPreset('system');

        expect($config->templateEngine)->toBe(TemplateEngineType::Arrowpipe)
            ->and($config->resourcePath)->toBe('prompts/system')
            ->and($config->extension)->toBe('.tpl');
    } finally {
        BasePath::set(getcwd() ?: $consumerRoot);
    }
});

function templatePresetDiscoveryConsumerRoot(): string {
    $dir = sys_get_temp_dir() . '/instructor-template-preset-discovery-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/composer.json', "{}\n");

    register_shutdown_function(static function () use ($dir): void {
        templatePresetDiscoveryDeleteDir($dir);
    });

    return $dir;
}

function templatePresetDiscoveryDeleteDir(string $dir): void {
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
            templatePresetDiscoveryDeleteDir($path);
            continue;
        }

        unlink($path);
    }

    rmdir($dir);
}
