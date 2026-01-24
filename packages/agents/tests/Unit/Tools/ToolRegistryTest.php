<?php declare(strict_types=1);

use Cognesy\Agents\Agent\Exceptions\InvalidToolException;
use Cognesy\Agents\Agent\Tools\MockTool;
use Cognesy\Agents\AgentBuilder\Capabilities\Tools\ToolPolicy;
use Cognesy\Agents\AgentBuilder\Capabilities\Tools\ToolRegistry;

it('registers and lists tool metadata', function () {
    $registry = new ToolRegistry();
    $registry->register(MockTool::returning('alpha', 'Alpha tool', 'ok'));

    $metadata = $registry->listMetadata();

    expect($metadata)->toHaveCount(1)
        ->and($metadata[0]['name'])->toBe('alpha');
});

it('resolves tools and builds collections', function () {
    $registry = new ToolRegistry();
    $registry->register(MockTool::returning('alpha', 'Alpha tool', 'ok'));
    $registry->register(MockTool::returning('beta', 'Beta tool', 'ok'));

    $tools = $registry->buildTools();

    expect($tools->names())->toEqual(['alpha', 'beta']);
});

it('applies tool policy when building collections', function () {
    $registry = new ToolRegistry();
    $registry->register(MockTool::returning('alpha', 'Alpha tool', 'ok'));
    $registry->register(MockTool::returning('beta', 'Beta tool', 'ok'));

    $policy = new ToolPolicy(allowlist: ['beta']);
    $tools = $registry->buildTools(policy: $policy);

    expect($tools->names())->toEqual(['beta']);
});

it('searches tools by name or description', function () {
    $registry = new ToolRegistry();
    $registry->register(MockTool::returning('alpha', 'Alpha tool', 'ok'));
    $registry->register(MockTool::returning('beta', 'Beta tool', 'ok'));

    $results = $registry->search('beta');

    expect($results)->toHaveCount(1)
        ->and($results[0]['name'])->toBe('beta');
});

it('throws when resolving missing tool', function () {
    $registry = new ToolRegistry();

    $resolve = fn () => $registry->resolve('missing');

    expect($resolve)->toThrow(InvalidToolException::class);
});
