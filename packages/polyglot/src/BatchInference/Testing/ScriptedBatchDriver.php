<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Testing;

use Closure;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanCancelBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanDriveBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanListBatchInference;
use Cognesy\Polyglot\BatchInference\Data\BatchCancellation;
use Cognesy\Polyglot\BatchInference\Data\BatchCapabilities;
use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchJob;
use Cognesy\Polyglot\BatchInference\Data\BatchJobPage;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use LogicException;

final readonly class ScriptedBatchDriver implements CanDriveBatchInference, CanCancelBatchInference, CanListBatchInference
{
    /**
     * @param Closure(BatchItems, ?BatchSubmissionOptions): BatchJob $onSubmit
     * @param Closure(BatchReference): BatchJob $onRetrieve
     * @param Closure(BatchReference): BatchResults $onResults
     * @param ?Closure(BatchReference): BatchCancellation $onCancel
     * @param ?Closure(int, ?BatchCursor): BatchJobPage $onList
     */
    public function __construct(
        private string $provider,
        private string $scope,
        private BatchCapabilities $capabilities,
        private Closure $onSubmit,
        private Closure $onRetrieve,
        private Closure $onResults,
        private ?Closure $onCancel = null,
        private ?Closure $onList = null,
    ) {
    }

    #[\Override]
    public function provider(): string
    {
        return $this->provider;
    }
    #[\Override]
    public function scope(): string
    {
        return $this->scope;
    }
    #[\Override]
    public function capabilities(): BatchCapabilities
    {
        return $this->capabilities;
    }

    #[\Override]
    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        return ($this->onSubmit)($items, $options);
    }

    #[\Override]
    public function retrieve(BatchReference $reference): BatchJob
    {
        return ($this->onRetrieve)($reference);
    }
    #[\Override]
    public function results(BatchReference $reference): BatchResults
    {
        return ($this->onResults)($reference);
    }

    #[\Override]
    public function cancel(BatchReference $reference): BatchCancellation
    {
        return ($this->onCancel ?? throw new LogicException('No cancellation script configured.'))($reference);
    }

    #[\Override]
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        return ($this->onList ?? throw new LogicException('No listing script configured.'))($limit, $cursor);
    }
}
