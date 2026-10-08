<?php

declare(strict_types=1);

use Cognesy\Polyglot\BatchInference\Results\JsonlRecordReader;

it('frames JSONL across arbitrary chunks and keeps UTF-8 intact', function () {
    $data = "{\"custom_id\":\"ą\"}\r\n\n{\"custom_id\":\"二\"}";
    $chunks = str_split($data, 1);

    expect(iterator_to_array((new JsonlRecordReader())->records($chunks)))->toBe([
        ['custom_id' => 'ą'],
        ['custom_id' => '二'],
    ]);
});

it('preserves preceding rows before a malformed final record fails', function () {
    $received = [];
    expect(function () use (&$received): void {
        foreach ((new JsonlRecordReader())->records(['{"ok":1}' . "\n" . '{"broken":']) as $record) {
            $received[] = $record;
        }
    })->toThrow(RuntimeException::class)
        ->and($received)->toBe([['ok' => 1]]);
});

it('rejects oversized records even without a final newline', function () {
    expect(fn () => iterator_to_array((new JsonlRecordReader(4))->records(['{"long":1}' . "\n"])))->toThrow(RuntimeException::class)
        ->and(fn () => iterator_to_array((new JsonlRecordReader(4))->records(['{"a":', '1}'])))->toThrow(RuntimeException::class);
});
