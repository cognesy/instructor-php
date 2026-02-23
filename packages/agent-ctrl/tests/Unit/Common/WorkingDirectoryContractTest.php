<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Bridge\ClaudeCodeBridge;
use Cognesy\AgentCtrl\Bridge\OpenCodeBridge;
use Cognesy\AgentCtrl\Builder\ClaudeCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\AgentCtrl\Builder\OpenCodeBridgeBuilder;

it('passes working directory to codex bridge', function () {
    $bridge = (new CodexBridgeBuilder())
        ->inDirectory('/tmp')
        ->build();

    expect(privateProperty($bridge, 'workingDirectory'))->toBe('/tmp');
});

it('passes working directory to claude bridge', function () {
    $bridge = (new ClaudeCodeBridgeBuilder())
        ->inDirectory('/tmp')
        ->build();

    expect(privateProperty($bridge, 'workingDirectory'))->toBe('/tmp');
});

it('passes working directory to opencode bridge', function () {
    $bridge = (new OpenCodeBridgeBuilder())
        ->inDirectory('/tmp')
        ->build();

    expect(privateProperty($bridge, 'workingDirectory'))->toBe('/tmp');
});

it('fails early when claude working directory does not exist', function () {
    $missingPath = '/tmp/agent-ctrl-missing-claude-' . uniqid('', true);
    $bridge = new ClaudeCodeBridge(workingDirectory: $missingPath);

    expect(fn() => $bridge->execute('test'))->toThrow(
        InvalidArgumentException::class,
        "Working directory does not exist: {$missingPath}",
    );
});

it('fails early when opencode working directory does not exist', function () {
    $missingPath = '/tmp/agent-ctrl-missing-opencode-' . uniqid('', true);
    $bridge = new OpenCodeBridge(workingDirectory: $missingPath);

    expect(fn() => $bridge->execute('test'))->toThrow(
        InvalidArgumentException::class,
        "Working directory does not exist: {$missingPath}",
    );
});

function privateProperty(object $object, string $property): mixed
{
    $reflection = new ReflectionClass($object);
    $refProperty = $reflection->getProperty($property);

    return $refProperty->getValue($object);
}
