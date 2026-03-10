<?php declare(strict_types=1);

use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\TransformationStream;

it('returns transformed output from getCompleted for non-empty streams', function () {
    $stream = TransformationStream::from([1, 2, 3])
        ->through(new Map(fn(int $x): int => $x * 2));

    expect($stream->getCompleted())->toBe([2, 4, 6]);
});

it('allows calling getIterator after getCompleted without double-completion failure', function () {
    $stream = TransformationStream::from([1, 2, 3])
        ->through(new Map(fn(int $x): int => $x * 2));

    $completed = $stream->getCompleted();
    $iterated = iterator_to_array($stream->getIterator(), false);

    expect($completed)->toBe([2, 4, 6]);
    expect($iterated)->toBe([2, 4, 6]);
});

it('allows repeated completion access with stable results', function () {
    $stream = TransformationStream::from([1, 2, 3])
        ->through(new Map(fn(int $x): int => $x * 2));

    $first = $stream->getCompleted();
    $second = $stream->getCompleted();

    expect($first)->toBe([2, 4, 6]);
    expect($second)->toBe([2, 4, 6]);
});

it('replays completed output from the materialized snapshot', function () {
    $stream = TransformationStream::from([1, 2, 3])
        ->through(new Map(fn(int $x): int => $x * 2));

    $completed = $stream->getCompleted();
    $firstReplay = iterator_to_array($stream->getIterator(), false);
    $secondReplay = iterator_to_array($stream->getIterator(), false);

    expect($completed)->toBe([2, 4, 6]);
    expect($firstReplay)->toBe([2, 4, 6]);
    expect($secondReplay)->toBe([2, 4, 6]);
});

it('allows getCompleted after consuming iterator without failure', function () {
    $stream = TransformationStream::from([1, 2, 3])
        ->through(new Map(fn(int $x): int => $x * 2));

    $iterated = iterator_to_array($stream->getIterator(), false);
    $completed = $stream->getCompleted();

    expect($iterated)->toBe([2, 4, 6]);
    expect($completed)->toBe([2, 4, 6]);
});
