<?php declare(strict_types=1);

namespace Examples\BatchInference\Support;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Data\BatchCancellation;
use Cognesy\Polyglot\BatchInference\Data\BatchCapabilities;
use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchInputSupport;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchItemFailure;
use Cognesy\Polyglot\BatchInference\Data\BatchItemProvenance;
use Cognesy\Polyglot\BatchInference\Data\BatchItemResult;
use Cognesy\Polyglot\BatchInference\Data\BatchJob;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchJobPage;
use Cognesy\Polyglot\BatchInference\Data\BatchProgress;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchInputKind;
use Cognesy\Polyglot\BatchInference\Enums\BatchItemFailureKind;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\BatchInference\Testing\ScriptedBatchDriver;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

final readonly class DemoBatch
{
    public static function reference(): BatchReference
    {
        return new BatchReference(
            new BatchJobId('batch-example'), 'demo', 'examples-batch',
            '/v1/chat/completions', 'demo-chat', 2, true,
        );
    }

    public static function items(): BatchItems
    {
        return BatchItems::of(
            BatchItem::of('row-1', new InferenceRequest(messages: Messages::fromString('A'))),
            BatchItem::of('row-2', new InferenceRequest(messages: Messages::fromString('B'))),
        );
    }

    public static function client(bool $partial = false, bool $canCancel = true): BatchInference
    {
        $reference = self::reference();
        $pending = self::job($reference, BatchStatus::Pending, BatchResultsAvailability::Pending);
        $current = self::job(
            $reference,
            $partial ? BatchStatus::Running : BatchStatus::Completed,
            $partial ? BatchResultsAvailability::Partial : BatchResultsAvailability::Final,
        );
        $success = new BatchItemResult(
            'row-1', Result::success(InferenceResponse::empty()),
            new BatchItemProvenance(['custom_id' => 'row-1'], 'demo-output'),
        );
        $failure = new BatchItemResult(
            'row-2', Result::failure(new BatchItemFailure(BatchItemFailureKind::ProviderError, 'invalid_request', 'Demo item error.')),
            new BatchItemProvenance(['custom_id' => 'row-2'], 'demo-error'),
        );
        $driver = new ScriptedBatchDriver(
            'demo', 'examples-batch',
            new BatchCapabilities($canCancel, true, $partial, [new BatchInputSupport(BatchInputKind::Inline, 2)]),
            static function (BatchItems $items, ?BatchSubmissionOptions $options) use ($pending): BatchJob {
                if (iterator_count($items->getIterator()) !== 2) {
                    throw new \InvalidArgumentException('Demo batch needs two items.');
                }
                return $pending;
            },
            static fn (BatchReference $reference): BatchJob => $current,
            static fn (BatchReference $reference): BatchResults => new BatchResults(
                $partial ? BatchResultsAvailability::Partial : BatchResultsAvailability::Final,
                $partial ? [$success] : [$success, $failure],
            ),
            static fn (BatchReference $reference): BatchCancellation => new BatchCancellation(
                $reference, true, false, new DateTimeImmutable('2026-10-07T10:30:00Z'), 'cancelling',
            ),
            static function (int $limit, ?BatchCursor $cursor) use ($current): BatchJobPage {
                if ($cursor !== null) {
                    return new BatchJobPage([]);
                }
                return new BatchJobPage([$current], new BatchCursor('next-demo', 'demo', 'examples-batch', $limit));
            },
        );

        return BatchInference::fromRuntime(new BatchRuntime($driver));
    }

    private static function job(BatchReference $reference, BatchStatus $status, BatchResultsAvailability $availability): BatchJob
    {
        $completed = $status === BatchStatus::Completed ? 1 : 0;
        $failed = $status === BatchStatus::Completed ? 1 : 0;
        return new BatchJob(
            $reference, $status, $status->value,
            new BatchProgress(total: 2, completed: $completed, failed: $failed),
            $availability, new DateTimeImmutable('2026-10-07T10:00:00Z'),
        );
    }
}
