<?php declare(strict_types=1);

use Cognesy\Utils\Arrays;

/**
 * fromAny() marked every object it had ever seen and never unmarked, so it could not
 * tell a cycle from a diamond. An object referenced twice from the same parent - the
 * ordinary shared-dependency shape - had its second occurrence replaced with
 * 'ref-cycle: ...' even though nothing recursed. Tracking only the objects on the
 * CURRENT descent path fixes it while still terminating on genuine cycles.
 */

it('expands an object referenced twice from the same parent', function () {
    $shared = new stdClass();
    $shared->v = 1;

    $root = new stdClass();
    $root->a = $shared;
    $root->b = $shared;

    expect(Arrays::fromAny($root))->toBe([
        'a' => ['v' => [1]],
        'b' => ['v' => [1]],
    ]);
});

it('expands a shared object reached through different depths', function () {
    $leaf = new stdClass();
    $leaf->n = 'x';

    $branch = new stdClass();
    $branch->deep = $leaf;

    $root = new stdClass();
    $root->near = $leaf;
    $root->far = $branch;

    expect(Arrays::fromAny($root))->toBe([
        'near' => ['n' => ['x']],
        'far' => ['deep' => ['n' => ['x']]],
    ]);
});

it('still breaks a direct self-reference', function () {
    $node = new stdClass();
    $node->self = $node;

    expect(Arrays::fromAny($node))->toBe(['self' => ['ref-cycle: stdClass']]);
});

it('still breaks an indirect cycle', function () {
    $a = new stdClass();
    $b = new stdClass();
    $a->next = $b;
    $b->back = $a;

    expect(Arrays::fromAny($a))->toBe(['next' => ['back' => ['ref-cycle: stdClass']]]);
});

it('terminates and reports a cycle for a self-referencing list of objects', function () {
    $node = new stdClass();
    $node->children = [$node];

    expect(Arrays::fromAny($node))->toBe(['children' => [['ref-cycle: stdClass']]]);
});

it('wraps scalars and nulls', function () {
    expect(Arrays::fromAny(5))->toBe([5]);
    expect(Arrays::fromAny(null))->toBe([null]);
    expect(Arrays::fromAny('s'))->toBe(['s']);
});
