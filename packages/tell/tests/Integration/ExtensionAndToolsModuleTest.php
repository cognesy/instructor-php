<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Composition\StandardTellProfile;
use Cognesy\Tell\Composition\TellHostBuilder;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Discovery\ComposerTellExtensionCatalogue;

it('reports malformed Composer extensions descriptively without mounting host modules', function (): void {
    $project = tellTestProject();
    $vendor = tellMalformedComposerVendor();
    $catalogue = (new ComposerTellExtensionCatalogue($vendor))->catalogue($project);

    expect($catalogue->diagnostics)->not->toBeEmpty()
        ->and($catalogue->accepted)->toHaveCount(0)
        ->and(array_map(static fn ($diagnostic): string => $diagnostic->code, $catalogue->diagnostics))
        ->each->toBe('extension_discovery_error');
});

it('dispatches direct tools through the standard host controlled path', function (): void {
    $project = tellTestProject();
    file_put_contents($project . '/visible.txt', "bounded content\n");
    $paths = standardHostPaths($project);
    $host = TellHostBuilder::fromProfile(StandardTellProfile::runtime(
        $project,
        $paths,
        static fn () => FakeAgentDriver::fromResponses('unused'),
    ))->boot();

    $result = $host->tools()->dispatch(
        TellToolRequest::invoke('read_file', ['path' => 'visible.txt'])->tools(['read_file']),
    );

    expect($result->success)->toBeTrue()
        ->and($result->execution())->toBe(['mode' => 'direct', 'inference' => false, 'durable' => false])
        ->and(json_encode($result->data, JSON_THROW_ON_ERROR))->toContain('bounded content');
});
