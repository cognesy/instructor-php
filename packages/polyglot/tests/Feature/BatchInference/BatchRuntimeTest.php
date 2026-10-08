<?php

declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Data\BatchCancellation;
use Cognesy\Polyglot\BatchInference\Data\BatchCapabilities;
use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItemProvenance;
use Cognesy\Polyglot\BatchInference\Data\BatchItemResult;
use Cognesy\Polyglot\BatchInference\Data\BatchJob;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchJobPage;
use Cognesy\Polyglot\BatchInference\Data\BatchProgress;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Exceptions\UnsupportedBatchOperation;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\BatchInference\Testing\ScriptedBatchDriver;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Utils\Result\Result;

it('runs all five operations while keeping snapshots and references independent', function () {
    $calls = [];
    $reference = new BatchReference(new BatchJobId('batch_demo'), 'test', 'workspace-a', '/v1/chat/completions', 'chat', 1, true);
    $pending = new BatchJob($reference, BatchStatus::Pending, 'validating', new BatchProgress(total: 1), BatchResultsAvailability::Pending, new DateTimeImmutable('2026-10-07T10:00:00Z'));
    $finished = new BatchJob($reference, BatchStatus::Completed, 'completed', new BatchProgress(total: 1, completed: 1), BatchResultsAvailability::Final, new DateTimeImmutable('2026-10-07T11:00:00Z'));
    $driver = new ScriptedBatchDriver(
        'test',
        'workspace-a',
        new BatchCapabilities(true, true, false),
        function (BatchItems $items) use (&$calls, $pending): BatchJob {
            $calls[] = 'submit';
            expect(iterator_count($items->getIterator()))->toBe(1);
            return $pending;
        },
        function (BatchReference $ref) use (&$calls, $finished): BatchJob {
            $calls[] = 'retrieve';
            return $finished;
        },
        function (BatchReference $ref) use (&$calls): BatchResults {
            $calls[] = 'results';
            return new BatchResults(BatchResultsAvailability::Final, [
                new BatchItemResult('item-1', Result::success(InferenceResponse::empty()), new BatchItemProvenance(['custom_id' => 'item-1'])),
            ]);
        },
        function (BatchReference $ref) use (&$calls): BatchCancellation {
            $calls[] = 'cancel';
            return new BatchCancellation($ref, true, false, new DateTimeImmutable('2026-10-07T10:10:00Z'));
        },
        function (int $limit, ?BatchCursor $cursor) use (&$calls, $finished): BatchJobPage {
            $calls[] = 'list';
            return new BatchJobPage([$finished], new BatchCursor('page-2', 'test', 'workspace-a', $limit));
        },
    );
    $batches = BatchInference::fromRuntime(new BatchRuntime($driver));
    $items = BatchItems::of(BatchItem::of('item-1', new InferenceRequest(messages: Messages::fromString('Hello'))));

    $submitted = $batches->submit($items);
    $submitted->status();
    $submitted->progress();
    $submitted->reference()->toArray();
    $submitted->toArray();
    expect($calls)->toBe(['submit']);
    $stored = json_decode(json_encode($submitted->reference()->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $resumed = BatchReference::fromArray($stored);
    $retrieved = $batches->retrieve($resumed);
    $cancelled = $batches->cancel($resumed);
    $results = $batches->results($resumed);
    $page = $batches->listJobs();

    expect($submitted->status())->toBe(BatchStatus::Pending)
        ->and($submitted->observedAt()->format(DATE_ATOM))->toBe('2026-10-07T10:00:00+00:00')
        ->and($retrieved->status())->toBe(BatchStatus::Completed)
        ->and($cancelled->acknowledged())->toBeTrue()
        ->and(iterator_to_array($results->items())[0]->result()->unwrap())->toBeInstanceOf(InferenceResponse::class)
        ->and(iterator_to_array($page->jobs())[0]->reference()->id()->toString())->toBe('batch_demo')
        ->and($page->nextCursor()?->token())->toBe('page-2')
        ->and($calls)->toBe(['submit', 'retrieve', 'cancel', 'results', 'list']);
});

it('rejects wrong scope, cursor binding, and unsupported cancellation before dispatch', function () {
    $calls = 0;
    $driver = new ScriptedBatchDriver(
        'test',
        'workspace-a',
        new BatchCapabilities(false, false, false),
        function () use (&$calls): never {
            ++$calls;
            throw new LogicException('unexpected');
        },
        function () use (&$calls): never {
            ++$calls;
            throw new LogicException('unexpected');
        },
        function () use (&$calls): never {
            ++$calls;
            throw new LogicException('unexpected');
        },
    );
    $batches = BatchInference::fromRuntime(new BatchRuntime($driver));
    $wrong = new BatchReference(new BatchJobId('batch-x'), 'test', 'workspace-b', 'chat', 'chat');
    $right = new BatchReference(new BatchJobId('batch-x'), 'test', 'workspace-a', 'chat', 'chat');

    expect(fn () => $batches->retrieve($wrong))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $batches->cancel($right))->toThrow(UnsupportedBatchOperation::class)
        ->and(fn () => $batches->listJobs(cursor: new BatchCursor('2', 'test', 'other', 50)))->toThrow(InvalidArgumentException::class)
        ->and($calls)->toBe(0);
});

it('rejects direct DeepSeek batch submission before constructing a network driver', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.deepseek.com',
        apiKey: 'test-secret',
        endpoint: '/chat/completions',
        model: 'deepseek-flash',
        driver: 'deepseek',
    );

    expect(fn () => BatchConfig::fromLLMConfig($config))
        ->toThrow(InvalidArgumentException::class, 'No native batch configuration');
});
