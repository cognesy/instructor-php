<?php

declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Contracts\CanEncodeBatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Preparation\BatchInputPreparer;
use Cognesy\Polyglot\BatchInference\Preparation\BatchRequestNormalizer;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

function batchInputTestEncoder(): CanEncodeBatchItem
{
    return new class () implements CanEncodeBatchItem {
        public function encode(BatchItem $item): array
        {
            return ['custom_id' => $item->key(), 'model' => $item->request()->model()];
        }
    };
}

function batchInputTestPreparer(int $maxItems = 10, int $maxBytes = 200000000): BatchInputPreparer
{
    return new BatchInputPreparer(
        new BatchRequestNormalizer('openai', 'gpt-batch-test'),
        batchInputTestEncoder(),
        maxItems: $maxItems,
        maxBytes: $maxBytes,
    );
}

function batchInputTestItem(string $key, bool $stream = false): BatchItem
{
    return BatchItem::of($key, new InferenceRequest(
        messages: Messages::fromString('test input'),
        options: $stream ? ['stream' => true] : [],
    ));
}

it('consumes an input producer once, resolves its model, and removes its spool', function () {
    $iterations = 0;
    $items = (static function () use (&$iterations): Generator {
        ++$iterations;
        yield batchInputTestItem('first');
        yield batchInputTestItem('second');
    })();

    $prepared = batchInputTestPreparer()->prepare(BatchItems::fromIterable($items));
    $path = $prepared->path();
    $lines = file($path, FILE_IGNORE_NEW_LINES);

    expect($iterations)->toBe(1)
        ->and($prepared->count())->toBe(2)
        ->and(json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR))->toBe(['custom_id' => 'first', 'model' => 'gpt-batch-test'])
        ->and(json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR))->toBe(['custom_id' => 'second', 'model' => 'gpt-batch-test']);

    $prepared->close();
    expect(file_exists($path))->toBeFalse();
});

it('rejects duplicate keys, streaming, and admitted item overflow before any provider call', function () {
    $preparer = batchInputTestPreparer();

    expect(fn () => $preparer->prepare(BatchItems::of(batchInputTestItem('same'), batchInputTestItem('same'))))
        ->toThrow(InvalidArgumentException::class, 'Duplicate')
        ->and(fn () => $preparer->prepare(BatchItems::of()))
        ->toThrow(InvalidArgumentException::class, 'at least one')
        ->and(fn () => batchInputTestItem(''))
        ->toThrow(InvalidArgumentException::class, 'nonempty')
        ->and(fn () => $preparer->prepare(BatchItems::of(batchInputTestItem('streaming', true))))
        ->toThrow(InvalidArgumentException::class, 'Streaming')
        ->and(fn () => batchInputTestPreparer(maxItems: 1)->prepare(BatchItems::of(batchInputTestItem('one'), batchInputTestItem('two'))))
        ->toThrow(InvalidArgumentException::class, 'item limit')
        ->and(fn () => batchInputTestPreparer(maxBytes: 10)->prepare(BatchItems::of(batchInputTestItem('one'))))
        ->toThrow(InvalidArgumentException::class, 'payload limit');
});

it('rejects online-only retry and response-cache policies before preparing a batch', function () {
    $request = new InferenceRequest(messages: Messages::fromString('test input'));
    $preparer = batchInputTestPreparer();

    expect(fn () => $preparer->prepare(BatchItems::of(
        BatchItem::of('retry', $request->withRetryPolicy(new InferenceRetryPolicy(maxAttempts: 2))),
    )))->toThrow(InvalidArgumentException::class, 'retry')
        ->and(fn () => $preparer->prepare(BatchItems::of(
            BatchItem::of('cache', $request->withResponseCachePolicy(ResponseCachePolicy::Memory)),
        )))->toThrow(InvalidArgumentException::class, 'cache');
});
