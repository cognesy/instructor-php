<?php

declare(strict_types=1);

use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchItemFailure;
use Cognesy\Polyglot\BatchInference\Data\BatchItemProvenance;
use Cognesy\Polyglot\BatchInference\Data\BatchItemResult;
use Cognesy\Polyglot\BatchInference\Data\BatchJob;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchProgress;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Utils\Result\Result;

it('round trips an opaque remote identity through a durable reference', function () {
    $id = 'arn:aws:bedrock:eu-central-1:123456789012:model-invocation-job/demo/path';
    $reference = new BatchReference(new BatchJobId($id), 'bedrock', 'eu-central-1', 'converse', 'bedrock-converse', 2, true);
    $serialized = json_decode(json_encode($reference->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $resumed = BatchReference::fromArray($serialized);

    expect($resumed->toArray())->toBe($reference->toArray())
        ->and($resumed->id()->toString())->toBe($id)
        ->and($serialized)->not->toHaveKey('apiKey');
});

it('keeps execution state and result availability independent', function () {
    $reference = new BatchReference(new BatchJobId('batch_01'), 'openai', 'account-a', '/v1/chat/completions', 'openai-chat', 3, true);
    $job = new BatchJob($reference, BatchStatus::Completed, 'completed', new BatchProgress(total: 3, completed: 2, failed: 1), BatchResultsAvailability::Final, new DateTimeImmutable('2026-10-07T12:00:00Z'));
    $resumed = BatchJob::fromArray($job->toArray());

    expect($job->isTerminal())->toBeTrue()
        ->and($resumed->progress()->processing())->toBeNull()
        ->and($resumed->progress()->failed())->toBe(1)
        ->and($resumed->resultsAvailability())->toBe(BatchResultsAvailability::Final)
        ->and(BatchStatus::Unknown->isTerminal())->toBeFalse();
});

it('rejects a pagination cursor outside its original query', function () {
    $cursor = BatchCursor::fromArray((new BatchCursor('next:2', 'mistral', 'workspace-a', 50))->toArray());

    $cursor->assertMatches('mistral', 'workspace-a', 50);
    expect(fn () => $cursor->assertMatches('mistral', 'workspace-b', 50))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $cursor->assertMatches('mistral', 'workspace-a', 100))->toThrow(InvalidArgumentException::class);
});

it('preserves a typed item failure and enforces one pass of available results', function () {
    $failure = new BatchItemFailure(BatchItemFailureKind::Expired, 'batch_expired', 'Window elapsed');
    $result = new BatchItemResult('document-7', Result::failure($failure), new BatchItemProvenance(['custom_id' => 'document-7'], 'file-1'));
    $items = (static function () use ($result): Generator {
        yield $result;
    })();
    $results = new BatchResults(BatchResultsAvailability::Partial, $items);

    expect($results->isAvailable())->toBeTrue()
        ->and($results->isFinal())->toBeFalse()
        ->and(iterator_to_array($results->items())[0]->result()->error())->toBe($failure)
        ->and(fn () => iterator_to_array($results->items()))->toThrow(LogicException::class);
});

it('does not expose unavailable results as an empty successful batch', function () {
    $results = new BatchResults(BatchResultsAvailability::Unavailable, [], 'result retention expired');

    expect($results->isAvailable())->toBeFalse()
        ->and($results->unavailableReason())->toBe('result retention expired')
        ->and(fn () => iterator_to_array($results->items()))->toThrow(LogicException::class);
});

it('closes a lazy result source when iteration stops early', function () {
    $closed = false;
    $failure = new BatchItemFailure(BatchItemFailureKind::ProviderError, 'test', 'test');
    $result = new BatchItemResult('one', Result::failure($failure), new BatchItemProvenance([]));
    $results = new BatchResults(BatchResultsAvailability::Partial, static function () use (&$closed, $result): Generator {
        try {
            yield $result;
            yield $result;
        } finally {
            $closed = true;
        }
    });

    foreach ($results->items() as $item) {
        expect($item)->toBe($result);
        break;
    }

    expect($closed)->toBeTrue();
});
