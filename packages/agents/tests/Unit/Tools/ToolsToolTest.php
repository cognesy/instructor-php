<?php declare(strict_types=1);

use Cognesy\Agents\Capability\Tools\ToolsTool;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Agents\Tool\Tools\MockTool;

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

it('searches tools by query', function () {
    $registry = new ToolRegistry();
    $registry->register(MockTool::returning('alpha', 'Alpha tool', 'ok'));
    $registry->register(MockTool::returning('beta', 'Beta tool', 'ok'));

    $tool = new ToolsTool($registry);
    $result = $tool(action: 'search', query: 'beta');

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(1)
        ->and($result['tools'][0]['name'])->toBe('beta');
});
