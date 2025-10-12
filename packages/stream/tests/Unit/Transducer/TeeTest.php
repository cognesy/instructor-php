<?php declare(strict_types=1);

use Cognesy\Stream\Support\Tee;

test('splits iterable into two independent iterators', function () {
    $source = [1, 2, 3, 4];
    [$branch1, $branch2] = Tee::split($source);

    $result1 = iterator_to_array($branch1);
    $result2 = iterator_to_array($branch2);

    expect($result1)->toBe([1, 2, 3, 4]);
    expect($result2)->toBe([1, 2, 3, 4]);
});

test('splits iterable into multiple branches', function () {
    $source = [1, 2, 3];
    [$b1, $b2, $b3] = Tee::split($source, 3);

    expect(iterator_to_array($b1))->toBe([1, 2, 3]);
    expect(iterator_to_array($b2))->toBe([1, 2, 3]);
    expect(iterator_to_array($b3))->toBe([1, 2, 3]);
});

test('branches can consume at different speeds', function () {
    $source = [1, 2, 3, 4, 5];
    [$fast, $slow] = Tee::split($source);

    expect($fast->current())->toBe(1);
    $fast->next();
    expect($fast->current())->toBe(2);
    $fast->next();

    expect($slow->current())->toBe(1);
    $slow->next();

    $fast->next();
    expect($fast->current())->toBe(4);

    expect($slow->current())->toBe(2);
});

test('abandoned branch does not block others', function () {
    $source = [1, 2, 3, 4];
    [$branch1, $branch2] = Tee::split($source);

    expect($branch1->current())->toBe(1);
    $branch1->next();

    unset($branch1);

    $result = iterator_to_array($branch2);
    expect($result)->toBe([1, 2, 3, 4]);
});

test('handles empty iterable', function () {
    $source = [];
    [$branch1, $branch2] = Tee::split($source);

    expect(iterator_to_array($branch1))->toBe([]);
    expect(iterator_to_array($branch2))->toBe([]);
});

test('handles single element iterable', function () {
    $source = [42];
    [$branch1, $branch2] = Tee::split($source);

    expect(iterator_to_array($branch1))->toBe([42]);
    expect(iterator_to_array($branch2))->toBe([42]);
});

test('works with generator', function () {
    $generator = function () {
        yield 1;
        yield 2;
        yield 3;
    };

    [$branch1, $branch2] = Tee::split($generator());

    expect(iterator_to_array($branch1))->toBe([1, 2, 3]);
    expect(iterator_to_array($branch2))->toBe([1, 2, 3]);
});

test('throws exception for invalid branch count', function () {
    Tee::split([1, 2, 3], 0);
})->throws(InvalidArgumentException::class, 'branches must be >= 1');

test('throws exception for negative branch count', function () {
    Tee::split([1, 2, 3], -1);
})->throws(InvalidArgumentException::class, 'branches must be >= 1');

test('preserves values exactly as they appear in source', function () {
    $source = ['a', null, false, 0, ''];
    [$branch1, $branch2] = Tee::split($source);

    expect(iterator_to_array($branch1))->toBe(['a', null, false, 0, '']);
    expect(iterator_to_array($branch2))->toBe(['a', null, false, 0, '']);
});

test('handles partial consumption of branches', function () {
    $source = [1, 2, 3, 4, 5];
    [$branch1, $branch2] = Tee::split($source);

    $partial1 = [];
    foreach ($branch1 as $val) {
        $partial1[] = $val;
        if ($val === 2) {
            break;
        }
    }

    expect($partial1)->toBe([1, 2]);
    expect(iterator_to_array($branch2))->toBe([1, 2, 3, 4, 5]);
});

test('single branch consumes entire source', function () {
    $source = [1, 2, 3];
    [$branch] = Tee::split($source, 1);

    expect(iterator_to_array($branch))->toBe([1, 2, 3]);
});

test('branches maintain iteration state independently', function () {
    $source = [10, 20, 30];
    [$b1, $b2] = Tee::split($source);

    expect($b1->current())->toBe(10);
    expect($b2->current())->toBe(10);

    $b1->next();
    expect($b1->current())->toBe(20);
    expect($b2->current())->toBe(10);

    $b2->next();
    $b2->next();
    expect($b1->current())->toBe(20);
    expect($b2->current())->toBe(30);
});
