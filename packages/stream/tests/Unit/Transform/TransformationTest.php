<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\ToArrayReducer;
use Cognesy\Stream\Sinks\ToStringReducer;
use Cognesy\Stream\Sources\Array\ArrayStream;
use Cognesy\Stream\Transform\Filter\Transducers\Filter;
use Cognesy\Stream\Transform\Limit\Transducers\TakeN;
use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\Transformation;

// STATIC FACTORY

test('define() creates transformation with transducers', function () {
    $transformation = Transformation::define(
        new Map(fn($x) => $x * 2),
        new Filter(fn($x) => $x > 5)
    );

    $result = $transformation
        ->withInput([1, 2, 3, 4, 5])
        ->execute();

    expect($result)->toBe([6, 8, 10]);
});

test('define() with no transducers creates empty transformation', function () {
    $transformation = Transformation::define();

    $result = $transformation
        ->withInput([1, 2, 3])
        ->execute();

    expect($result)->toBe([1, 2, 3]);
});

// TRANSDUCER INTERFACE

test('Transformation implements Transducer interface', function () {
    $transformation = Transformation::define(new Map(fn($x) => $x * 2));

    expect($transformation)->toBeInstanceOf(\Cognesy\Stream\Contracts\Transducer::class);
});

test('__invoke() returns composed Reducer', function () {
    $transformation = Transformation::define(
        new Map(fn($x) => $x * 2),
        new Filter(fn($x) => $x > 5)
    );

    $reducer = ($transformation)(new ToArrayReducer());

    expect($reducer)->toBeInstanceOf(\Cognesy\Stream\Contracts\Reducer::class);
});

test('Transformation can be used as transducer in another Transformation', function () {
    $normalize = Transformation::define(
        new Map(fn($x) => trim($x)),
        new Filter(fn($x) => strlen($x) > 0)
    );

    $result = Transformation::define($normalize)
        ->through(new Map(fn($x) => strtoupper($x)))
        ->withInput(['  foo  ', '', '  bar  '])
        ->execute();

    expect($result)->toBe(['FOO', 'BAR']);
});

// COMPOSITION: before()

test('before() prepends current transformation to provided transformation', function () {
    $normalize = Transformation::define(
        new Map(fn($x) => trim($x))
    );

    $uppercase = Transformation::define(
        new Map(fn($x) => strtoupper($x))
    );

    // Apply normalize BEFORE uppercase
    $pipeline = $uppercase->before($normalize);
    $result = $pipeline->withInput(['  foo  ', '  bar  '])->execute();

    // Execution order: trim → uppercase
    expect($result)->toBe(['FOO', 'BAR']);
});

test('before() inherits sink from current transformation', function () {
    $normalize = Transformation::define(new Map(fn($x) => trim($x)));
    $uppercase = Transformation::define(new Map(fn($x) => strtoupper($x)))
        ->withSink(new ToStringReducer(separator: ','));

    $pipeline = $uppercase->before($normalize);
    $result = $pipeline->withInput(['  foo  ', '  bar  '])->execute();

    expect($result)->toBe('FOO,BAR');
});

test('before() inherits source from current transformation', function () {
    $source = ArrayStream::from(['  foo  ', '  bar  ']);

    $normalize = Transformation::define(new Map(fn($x) => trim($x)));
    $uppercase = Transformation::define(new Map(fn($x) => strtoupper($x)))
        ->withInput($source);

    $pipeline = $uppercase->before($normalize);
    $result = $pipeline->execute();

    expect($result)->toBe(['FOO', 'BAR']);
});

test('before() with multiple transformations in sequence', function () {
    $trim = Transformation::define(new Map(fn($x) => trim($x)));
    $filter = Transformation::define(new Filter(fn($x) => strlen($x) > 0));
    $upper = Transformation::define(new Map(fn($x) => strtoupper($x)));

    // Build: upper ← filter ← trim
    $pipeline = $upper
        ->before($filter)
        ->before($trim);

    $result = $pipeline->withInput(['  foo  ', '', '  bar  '])->execute();

    // Execution: trim → filter → upper
    expect($result)->toBe(['FOO', 'BAR']);
});

// COMPOSITION: after()

test('after() appends provided transformation to current transformation', function () {
    $normalize = Transformation::define(
        new Map(fn($x) => trim($x))
    );

    $uppercase = Transformation::define(
        new Map(fn($x) => strtoupper($x))
    );

    // Apply uppercase AFTER normalize
    $pipeline = $uppercase->after($normalize);
    $result = $pipeline->withInput(['  foo  ', '  bar  '])->execute();

    // Execution order: uppercase → trim = UPPERCASE → trim
    expect($result)->toBe(['FOO', 'BAR']);
});

test('after() inherits sink from provided transformation', function () {
    $normalize = Transformation::define(new Map(fn($x) => trim($x)))
        ->withSink(new ToStringReducer(separator: '|'));
    $uppercase = Transformation::define(new Map(fn($x) => strtoupper($x)));

    $pipeline = $uppercase->after($normalize);
    $result = $pipeline->withInput(['foo', 'bar'])->execute();

    expect($result)->toBe('FOO|BAR');
});

test('after() inherits source from provided transformation', function () {
    $source = ArrayStream::from(['foo', 'bar']);

    $normalize = Transformation::define(new Map(fn($x) => trim($x)))
        ->withInput($source);
    $uppercase = Transformation::define(new Map(fn($x) => strtoupper($x)));

    $pipeline = $uppercase->after($normalize);
    $result = $pipeline->execute();

    expect($result)->toBe(['FOO', 'BAR']);
});

test('after() with multiple transformations in sequence', function () {
    $trim = Transformation::define(new Map(fn($x) => trim($x)));
    $filter = Transformation::define(new Filter(fn($x) => strlen($x) > 0));
    $upper = Transformation::define(new Map(fn($x) => strtoupper($x)));

    // Build: trim → filter → upper
    $pipeline = $trim
        ->after($filter)
        ->after($upper);

    $result = $pipeline->withInput(['  foo  ', '', '  bar  '])->execute();

    // Execution: trim → filter → upper
    expect($result)->toBe(['FOO', 'BAR']);
});

// NESTED COMPOSITION

test('deeply nested Transformation composition', function () {
    $level1 = Transformation::define(new Map(fn($x) => $x + 1));
    $level2 = Transformation::define($level1, new Map(fn($x) => $x * 2));
    $level3 = Transformation::define($level2, new Filter(fn($x) => $x > 10));

    $result = $level3->withInput([1, 2, 3, 4, 5, 6])->execute();

    // (1+1)*2=4, (2+1)*2=6, (3+1)*2=8, (4+1)*2=10, (5+1)*2=12, (6+1)*2=14
    // Filter >10: [12, 14]
    expect($result)->toBe([12, 14]);
});

test('composition of composed transformations', function () {
    $sanitize = Transformation::define(
        new Map(fn($x) => trim($x)),
        new Filter(fn($x) => strlen($x) > 0)
    );

    $enrich = Transformation::define(
        new Map(fn($x) => strtoupper($x)),
        new Map(fn($x) => "[$x]")
    );

    $combined = Transformation::define($sanitize, $enrich);

    $result = $combined->withInput(['  foo  ', '', '  bar  '])->execute();

    expect($result)->toBe(['[FOO]', '[BAR]']);
});

// COMPLEX SCENARIOS

test('before() and after() can be mixed', function () {
    $trim = Transformation::define(new Map(fn($x) => trim($x)));
    $upper = Transformation::define(new Map(fn($x) => strtoupper($x)));
    $bracket = Transformation::define(new Map(fn($x) => "[$x]"));

    // trim → upper → bracket
    $pipeline = $upper
        ->before($trim)
        ->after($bracket);

    $result = $pipeline->withInput(['  foo  ', '  bar  '])->execute();

    expect($result)->toBe(['[FOO]', '[BAR]']);
});

test('transformation composition with early termination', function () {
    $increment = Transformation::define(new Map(fn($x) => $x + 1));
    $multiply = Transformation::define(new Map(fn($x) => $x * 2));
    $takeTwo = Transformation::define(new TakeN(2));

    $pipeline = $increment
        ->after($multiply)
        ->after($takeTwo);

    $result = $pipeline->withInput([1, 2, 3, 4, 5])->execute();

    // (1+1)*2=4, (2+1)*2=6, then take 2
    expect($result)->toBe([4, 6]);
});

test('reusable transformation applied to multiple streams', function () {
    $pipeline = Transformation::define(
        new Map(fn($x) => $x * 2),
        new Filter(fn($x) => $x > 5),
        new TakeN(2)
    );

    $result1 = $pipeline->withInput([1, 2, 3, 4, 5])->execute();
    $result2 = $pipeline->withInput([5, 6, 7, 8, 9])->execute();

    expect($result1)->toBe([6, 8]);
    expect($result2)->toBe([10, 12]);
});

test('transformation composition preserves immutability', function () {
    $original = Transformation::define(new Map(fn($x) => $x * 2));
    $extended = $original->through(new Filter(fn($x) => $x > 5));

    $result1 = $original->withInput([1, 2, 3, 4, 5])->execute();
    $result2 = $extended->withInput([1, 2, 3, 4, 5])->execute();

    expect($result1)->toBe([2, 4, 6, 8, 10]);
    expect($result2)->toBe([6, 8, 10]);
});

test('empty transformation with composition', function () {
    $empty = Transformation::define();
    $withOps = $empty->through(
        new Map(fn($x) => $x * 2),
        new Filter(fn($x) => $x > 3)
    );

    $result = $withOps->withInput([1, 2, 3])->execute();

    expect($result)->toBe([4, 6]);
});

test('transformation as building block maintains correctness', function () {
    // Low-level transformation
    $numbers = Transformation::define(new Filter(fn($x) => is_numeric($x)));

    // Mid-level transformation using low-level
    $positive = Transformation::define(
        $numbers,
        new Filter(fn($x) => $x > 0)
    );

    // High-level transformation using mid-level
    $smallPositive = Transformation::define(
        $positive,
        new Filter(fn($x) => $x < 10)
    );

    $result = $smallPositive->withInput([-5, 'foo', 3, 15, 7, 'bar', 0])->execute();

    expect($result)->toBe([3, 7]);
});
