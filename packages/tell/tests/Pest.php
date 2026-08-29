<?php

declare(strict_types=1);

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Configuration\TellCredentialStore;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Discovery\StartupScanCounter;
use Cognesy\Tell\Runtime\TellAgentFactory;

/** @var list<string> $tellTemporaryRoots */
$tellTemporaryRoots = [];

/**
 * @param  callable(AgentLoop): AgentLoop|null  $decorate
 * @param  array<string, string>  $userAgents
 * @param  array<string, string>  $credentials
 */
function tellTestFactory(
    ?callable $decorate = null,
    array $userAgents = [],
    array $credentials = ['OPENAI_API_KEY' => 'tell-test-key'],
    ?StartupScanCounter $startupScans = null,
    ?CanUseTools $driver = null,
    ?string $composerVendorDir = null,
    ?string $rootComposerPath = null,
): TellAgentFactory {
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir() . '/instructor-tell-' . bin2hex(random_bytes(8));
    $package = $root . '/package-agents';
    $paths = new TellPaths($package, $root . '/tell-home');
    mkdir($package, 0755, true);
    mkdir($paths->userAgents, 0755, true);
    file_put_contents($package . '/default.md', <<<'MD'
---
name: default
label: Tell Test
description: Deterministic test agent
capabilities:
  - use_subagents
  - tell.coding
  - tell.ask_user
  - tell.system_prompt
  - tell.self_knowledge
  - tell.self_description
  - tell.agent_definitions
---

You are a deterministic test agent.
MD);
    foreach ($userAgents as $filename => $content) {
        file_put_contents($paths->userAgents . '/' . $filename, $content);
    }
    $credentialStore = new TellCredentialStore($paths);
    foreach ($credentials as $variable => $value) {
        $credentialStore->set($variable, $value);
    }
    $tellTemporaryRoots[] = $root;

    return new TellAgentFactory(
        paths: $paths,
        decorateLoop: $decorate,
        driver: $driver,
        startupScans: $startupScans,
        composerVendorDir: $composerVendorDir,
        rootComposerPath: $rootComposerPath,
    );
}

function tellLastTemporaryRoot(): string {
    global $tellTemporaryRoots;

    return $tellTemporaryRoots[array_key_last($tellTemporaryRoots)];
}

function tellTestProject(): string {
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir() . '/instructor-tell-' . bin2hex(random_bytes(8));
    mkdir($root, 0755, true);
    $tellTemporaryRoots[] = $root;

    return $root;
}

function standardHostPaths(string $project): TellPaths {
    $paths = new TellPaths($project . '/package-agents', $project . '/.tell-host');
    mkdir($paths->packageAgents, 0755, true);
    file_put_contents($paths->packageAgents . '/default.md', <<<'MD'
---
name: default
label: Standard Host Test
description: Deterministic standard host agent
capabilities:
  - tell.coding
---

You are deterministic.
MD);

    return $paths;
}

function tellMalformedComposerVendor(): string {
    $root = tellTestProject();
    $vendor = $root . '/vendor';
    mkdir($vendor . '/composer', 0755, true);
    file_put_contents($vendor . '/composer/installed.json', json_encode([
        'packages' => [[
            'name' => 'example/malformed-extension',
            'extra' => [
                'cognesy-agents' => [
                    'capabilities' => ['not-a-name-to-class-map'],
                ],
            ],
        ]],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

    return $vendor;
}

function tellRemoveDirectory(string $directory): void {
    if (!str_starts_with($directory, sys_get_temp_dir() . '/instructor-tell-') || !is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

afterEach(function (): void {
    global $tellTemporaryRoots;
    // Null until some test asks for a temporary root, which a test file that
    // touches no filesystem never does.
    foreach ($tellTemporaryRoots ?? [] as $root) {
        tellRemoveDirectory($root);
    }
    $tellTemporaryRoots = [];
});
