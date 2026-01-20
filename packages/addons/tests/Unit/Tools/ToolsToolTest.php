<?php declare(strict_types=1);

use Cognesy\Addons\AgentBuilder\Capabilities\Tools\ToolRegistry;
use Cognesy\Addons\AgentBuilder\Capabilities\Tools\ToolsTool;
use Cognesy\Addons\Agent\Tools\Testing\MockTool;

it('lists available tools', function () {
    $registry = new ToolRegistry();
    $registry->register(MockTool::returning('alpha', 'Alpha tool', 'ok'));

    $tool = new ToolsTool($registry);
    $result = $tool(action: 'list');

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(1)
        ->and($result['tools'][0]['name'])->toBe('alpha');
});

it('returns tool help', function () {
    $registry = new ToolRegistry();
    $registry->register(MockTool::returning('alpha', 'Alpha tool', 'ok'));

    $tool = new ToolsTool($registry);
    $result = $tool(action: 'help', tool: 'alpha');

    expect($result['success'])->toBeTrue()
        ->and($result['tool']['name'])->toBe('alpha');
});

it('searches tools with command fallback', function () {
    $registry = new ToolRegistry();
    $registry->register(MockTool::returning('alpha', 'Alpha tool', 'ok'));
    $registry->register(MockTool::returning('beta', 'Beta tool', 'ok'));

    $tool = new ToolsTool($registry);
    $result = $tool(command: '--search beta');

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(1)
        ->and($result['tools'][0]['name'])->toBe('beta');
});
