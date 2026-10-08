<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Logging\EventLog;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanCancelBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanDriveBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanListBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Contracts\CanSendBatchFileBody;
use Cognesy\Polyglot\BatchInference\Creation\BatchDriverRegistry;
use Cognesy\Polyglot\BatchInference\Data\BatchCancellation;
use Cognesy\Polyglot\BatchInference\Data\BatchCapabilities;
use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchJob;
use Cognesy\Polyglot\BatchInference\Data\BatchJobPage;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchCancellationException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchHttpException;
use Cognesy\Polyglot\BatchInference\Exceptions\UnsupportedBatchOperation;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\BatchInference\Transport\CurlBatchFileUploader;
use Cognesy\Polyglot\BatchInference\Transport\CurlBatchFileBodySender;
use Exception;
use InvalidArgumentException;

final readonly class BatchRuntime
{
    public function __construct(private CanDriveBatchInference $driver)
    {
    }

    public static function fromConfig(
        BatchConfig $config,
        ?BatchDriverRegistry $drivers = null,
        ?CanSendHttpRequests $http = null,
        ?CanUploadBatchFile $uploader = null,
        ?CanSendBatchFileBody $bodySender = null,
    ): self {
        $events = EventLog::root('polyglot.batch.runtime');
        $http ??= (new HttpClientBuilder(events: $events))->create();
        $uploader ??= new CurlBatchFileUploader();
        $bodySender ??= new CurlBatchFileBodySender();
        return new self(($drivers ?? BatchDriverRegistry::default())->makeDriver($config, $http, $uploader, $bodySender));
    }

    public function capabilities(): BatchCapabilities
    {
        return $this->driver->capabilities();
    }

    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        if ($options !== null && $options->provider() !== $this->driver->provider()) {
            throw new InvalidArgumentException('Batch submission options belong to a different provider.');
        }
        return $this->driver->submit($items, $options);
    }

    public function retrieve(BatchReference $reference): BatchJob
    {
        $this->assertReferenceScope($reference);
        return $this->driver->retrieve($reference);
    }

    public function cancel(BatchReference $reference): BatchCancellation
    {
        $this->assertReferenceScope($reference);
        if (!$this->driver->capabilities()->canCancel() || !$this->driver instanceof CanCancelBatchInference) {
            throw new UnsupportedBatchOperation('This provider has no documented batch cancellation operation.');
        }
        try {
            return $this->driver->cancel($reference);
        } catch (InvalidArgumentException $error) {
            throw $error;
        } catch (BatchCancellationException $error) {
            throw $error;
        } catch (Exception $error) {
            $status = match (true) {
                $error instanceof BatchHttpException => $error->statusCode(),
                $error instanceof HttpRequestException => $error->getStatusCode(),
                default => null,
            };
            $certainty = match (true) {
                $status !== null && $status >= 400 && $status < 500 && $status !== 408 => BatchMutationCertainty::Rejected,
                default => BatchMutationCertainty::MayHaveSucceeded,
            };
            throw new BatchCancellationException($reference, $certainty, $error);
        }
    }

    public function results(BatchReference $reference): BatchResults
    {
        $this->assertReferenceScope($reference);
        return $this->driver->results($reference);
    }

    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Batch listing limit must be between 1 and 1000.');
        }
        $cursor?->assertMatches($this->driver->provider(), $this->driver->scope(), $limit);
        if (!$this->driver->capabilities()->canList() || !$this->driver instanceof CanListBatchInference) {
            throw new UnsupportedBatchOperation('This provider has no documented batch listing operation.');
        }
        return $this->driver->listJobs($limit, $cursor);
    }

    private function assertReferenceScope(BatchReference $reference): void
    {
        if ($reference->provider() !== $this->driver->provider() || $reference->scope() !== $this->driver->scope()) {
            throw new InvalidArgumentException('Batch reference belongs to a different provider or connection scope.');
        }
    }
}
