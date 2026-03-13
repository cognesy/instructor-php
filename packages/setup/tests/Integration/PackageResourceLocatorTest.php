<?php declare(strict_types=1);

use Cognesy\Setup\Resources\PackageResourceLocator;

it('discovers package resources and maps destinations', function () {
    $root = sys_get_temp_dir() . '/setup-locator-' . uniqid('', true);
    mkdir($root . '/packages/polyglot/resources/config', 0777, true);
    mkdir($root . '/packages/http-client/resources/config', 0777, true);
    mkdir($root . '/packages/empty', 0777, true);

    file_put_contents($root . '/packages/polyglot/composer.json', '{}');
    file_put_contents($root . '/packages/polyglot/resources/config/llm.yaml', "driver: openai\n");
    file_put_contents($root . '/packages/http-client/composer.json', '{}');
    file_put_contents($root . '/packages/http-client/resources/config/http.yaml', "driver: curl\n");
    file_put_contents($root . '/packages/empty/composer.json', '{}');

    $locator = new PackageResourceLocator();
    $resources = $locator->locate($root . '/packages', $root . '/published');

    expect($resources)->toHaveCount(2);
    expect(array_map(fn($resource) => $resource->package, $resources))->toBe(['http-client', 'polyglot']);
    expect($resources[0]->destinationPath)->toContain('/published/http-client');
    expect($resources[1]->destinationPath)->toContain('/published/polyglot');
});

it('supports package inclusion and exclusion filters', function () {
    $root = sys_get_temp_dir() . '/setup-locator-filter-' . uniqid('', true);
    mkdir($root . '/packages/polyglot/resources/config', 0777, true);
    mkdir($root . '/packages/http-client/resources/config', 0777, true);
    file_put_contents($root . '/packages/polyglot/composer.json', '{}');
    file_put_contents($root . '/packages/polyglot/resources/config/llm.yaml', "driver: openai\n");
    file_put_contents($root . '/packages/http-client/composer.json', '{}');
    file_put_contents($root . '/packages/http-client/resources/config/http.yaml', "driver: curl\n");

    $locator = new PackageResourceLocator();
    $included = $locator->locate($root . '/packages', $root . '/published', ['polyglot'], []);
    $excluded = $locator->locate($root . '/packages', $root . '/published', [], ['polyglot']);

    expect($included)->toHaveCount(1);
    expect($included[0]->package)->toBe('polyglot');
    expect($excluded)->toHaveCount(1);
    expect($excluded[0]->package)->toBe('http-client');
});

