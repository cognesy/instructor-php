<?php declare(strict_types=1);

use Cognesy\Agents\Exceptions\InvalidToolException;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Agents\Tool\Tools\MockTool;

it('registers tool instances', function () {
    $registry = new ToolRegistry();
    $alpha = MockTool::returning('alpha', 'Alpha tool', 'ok');
    $registry->register($alpha);

    expect($registry->has('alpha'))->toBeTrue()
        ->and($registry->get('alpha'))->toBe($alpha);
});

it('registers tool factories', function () {
    $registry = new ToolRegistry();
    $registry->registerFactory('alpha', fn() => MockTool::returning('alpha', 'Alpha tool', 'ok'));

    expect($registry->has('alpha'))->toBeTrue()
        ->and($registry->get('alpha')->name())->toBe('alpha');
});

it('returns registered names', function () {
    $registry = new ToolRegistry();
    $registry->register(MockTool::returning('alpha', 'Alpha tool', 'ok'));
    $registry->registerFactory('beta', fn() => MockTool::returning('beta', 'Beta tool', 'ok'));

    expect($registry->names())->toEqual(['beta', 'alpha']);
});

it('throws when getting missing tool', function () {
    $registry = new ToolRegistry();

    $resolve = fn () => $registry->get('missing');

    expect($resolve)->toThrow(InvalidToolException::class);
});
