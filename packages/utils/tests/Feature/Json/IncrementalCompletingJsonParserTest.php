<?php declare(strict_types=1);

use Cognesy\Utils\Json\IncrementalCompletingJsonParser;

it('completes a growing object stream into valid json and array snapshots', function () {
    $parser = new IncrementalCompletingJsonParser();

    $parser->append('{"name":"Jo');
    expect($parser->currentJson())->toBe('{"name":"Jo"}');
    expect($parser->currentArray())->toBe(['name' => 'Jo']);

    $parser->append('hn","age":3');
    expect($parser->currentJson())->toBe('{"name":"John","age":3}');
    expect($parser->currentArray())->toBe(['name' => 'John', 'age' => 3]);

    $parser->append('0}');
    expect($parser->currentJson())->toBe('{"name":"John","age":30}');
    expect($parser->currentArray())->toBe(['name' => 'John', 'age' => 30]);
});

it('completes incomplete keys and values without reparsing generic json wrappers', function () {
    $parser = new IncrementalCompletingJsonParser();

    $parser->append('{"na');
    expect($parser->completionSuffix())->toBe('":null}');
    expect($parser->currentArray())->toBe(['na' => null]);

    $parser->append('me":"Ann');
    expect($parser->currentArray())->toBe(['name' => 'Ann']);
});

it('keeps the last successful array when the stream is currently after a trailing comma', function () {
    $parser = new IncrementalCompletingJsonParser();

    $parser->append('{"name":"Ann"');
    expect($parser->currentArray())->toBe(['name' => 'Ann']);

    $parser->append(',');
    expect($parser->currentJson())->toBeNull();
    expect($parser->currentArray())->toBe(['name' => 'Ann']);
});

it('keeps the last successful array when an array is waiting for the next value', function () {
    $parser = new IncrementalCompletingJsonParser();

    $parser->append('{"items":[1');
    expect($parser->currentArray())->toBe(['items' => [1]]);

    $parser->append(',');
    expect($parser->currentJson())->toBeNull();
    expect($parser->currentArray())->toBe(['items' => [1]]);
});

it('supports nested arrays and objects across chunk boundaries', function () {
    $parser = new IncrementalCompletingJsonParser();

    $parser->append('{"items":[{"name":"A');
    expect($parser->currentArray())->toBe([
        'items' => [
            ['name' => 'A'],
        ],
    ]);

    $parser->append('nn"},{"name":"Bob"}]}');
    expect($parser->currentArray())->toBe([
        'items' => [
            ['name' => 'Ann'],
            ['name' => 'Bob'],
        ],
    ]);
});

it('handles dangling escape sequences and unicode escape prefixes inside strings', function () {
    $parser = new IncrementalCompletingJsonParser();

    $parser->append('{"text":"a\\');
    expect($parser->currentJson())->toBe('{"text":"a\\""}');
    expect($parser->currentArray())->toBe(['text' => 'a"']);

    $parser->reset();
    $parser->append('{"text":"\\u00');
    expect($parser->currentJson())->toBe('{"text":"\\u0000"}');
    expect($parser->currentArray())->toBe(['text' => "\u{0000}"]);
});

it('resets state fully', function () {
    $parser = new IncrementalCompletingJsonParser();

    $parser->append('{"name":"Ann"}');
    expect($parser->currentArray())->toBe(['name' => 'Ann']);

    $parser->reset();
    expect($parser->buffer())->toBe('');
    expect($parser->currentJson())->toBeNull();
    expect($parser->currentArray())->toBeNull();
});

it('does not decode scalar roots into arrays', function () {
    $parser = new IncrementalCompletingJsonParser();

    $parser->append('"hello');
    expect($parser->currentJson())->toBe('"hello"');
    expect($parser->currentArray())->toBeNull();
});
