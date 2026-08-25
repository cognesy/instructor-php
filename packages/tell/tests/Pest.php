<?php

declare(strict_types=1);

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellCredentialStore;
use Cognesy\Tell\Runtime\TellPaths;

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
): TellAgentFactory {
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir().'/instructor-tell-'.bin2hex(random_bytes(8));
    $package = $root.'/package-agents';
    $paths = new TellPaths($package, $root.'/tell-home');
    mkdir($package, 0755, true);
    mkdir($paths->userAgents, 0755, true);
    file_put_contents($package.'/default.md', <<<'MD'
---
name: default
label: Tell Test
description: Deterministic test agent
capabilities:
  - tell.coding
  - tell.system_prompt
  - tell.self_knowledge
  - tell.self_description
  - tell.agent_definitions
---

You are a deterministic test agent.
MD);
    foreach ($userAgents as $filename => $content) {
        file_put_contents($paths->userAgents.'/'.$filename, $content);
    }
    $credentialStore = new TellCredentialStore($paths);
    foreach ($credentials as $variable => $value) {
        $credentialStore->set($variable, $value);
    }
    $tellTemporaryRoots[] = $root;

    return new TellAgentFactory($paths, $decorate);
}

function tellLastTemporaryRoot(): string
{
    global $tellTemporaryRoots;

    return $tellTemporaryRoots[array_key_last($tellTemporaryRoots)];
}

function tellRemoveDirectory(string $directory): void
{
    if (! str_starts_with($directory, sys_get_temp_dir().'/instructor-tell-') || ! is_dir($directory)) {
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
    foreach ($tellTemporaryRoots as $root) {
        tellRemoveDirectory($root);
    }
    $tellTemporaryRoots = [];
});
