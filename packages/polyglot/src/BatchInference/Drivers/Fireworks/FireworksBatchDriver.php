<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Fireworks;

use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Config\FireworksBatchSettings;
use Cognesy\Polyglot\BatchInference\Contracts\CanDriveBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanListBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchCapabilities;
use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchInputSupport;
use Cognesy\Polyglot\BatchInference\Data\BatchJob;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchJobPage;
use Cognesy\Polyglot\BatchInference\Data\BatchProgress;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchInputKind;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchHttpException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\BatchInference\Exceptions\UnsupportedBatchOperation;
use Cognesy\Polyglot\BatchInference\Preparation\BatchInputPreparer;
use Cognesy\Polyglot\BatchInference\Preparation\BatchRequestNormalizer;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use stdClass;
use Throwable;

final readonly class FireworksBatchDriver implements CanDriveBatchInference, CanListBatchInference
{
    private const ROUTE = '/v1/chat/completions';
    private const CODEC = 'fireworks-unqualified';

    public function __construct(
        private LLMConfig $inference,
        private FireworksBatchSettings $settings,
        private BatchHttpTransport $http,
        private CanUploadBatchFile $uploader,
    ) {
        if ($inference->driver !== 'fireworks' || $inference->apiKey === '' || $inference->model === '') {
            throw new InvalidArgumentException('Fireworks batch requires a Fireworks inference config with API key and model.');
        }
    }

    #[Override]
    public function provider(): string
    {
        return 'fireworks';
    }

    #[Override]
    public function scope(): string
    {
        return $this->settings->scope();
    }

    #[Override]
    public function capabilities(): BatchCapabilities
    {
        return new BatchCapabilities(false, true, false, [
            new BatchInputSupport(BatchInputKind::File, 50000, 149000000, 10000000),
        ], canReadResults: false);
    }

    #[Override]
    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        if ($options !== null) {
            throw new InvalidArgumentException('Fireworks batch submission options are not yet supported.');
        }
        $input = (new BatchInputPreparer(
            new BatchRequestNormalizer('fireworks', $this->inference->model),
            new FireworksBatchItemEncoder($this->inference),
            maxItems: 50000,
            maxBytes: 149000000,
        ))->prepare($items);
        $suffix = bin2hex(random_bytes(12));
        $datasetId = 'polyglot-' . $suffix . '-input';
        $jobId = 'polyglot-' . $suffix;
        $inputDataset = $this->resource('datasets', $datasetId);
        $outputDataset = $this->resource('datasets', $jobId . '-output');
        $jobName = $this->resource('batchInferenceJobs', $jobId);

        try {
            $this->createDataset($datasetId, $inputDataset, $input->count());
            $this->uploadDataset($datasetId, $input->path(), $inputDataset);
            $data = $this->createJob($jobId, $jobName, $inputDataset, $outputDataset);
            $reference = new BatchReference(
                new BatchJobId($jobName),
                'fireworks',
                $this->scope(),
                self::ROUTE,
                self::CODEC,
                $input->count(),
                true,
            );
            return $this->job($data, $reference);
        } finally {
            $input->close();
        }
    }

    #[Override]
    public function retrieve(BatchReference $reference): BatchJob
    {
        $this->assertReference($reference);
        $id = substr($reference->id()->toString(), strlen($this->resource('batchInferenceJobs') . '/'));
        $data = $this->http->json('GET', $this->url('/' . $this->resource('batchInferenceJobs', $id)), $this->headers());
        return $this->job($data, $reference);
    }

    #[Override]
    public function results(BatchReference $reference): BatchResults
    {
        $this->assertReference($reference);
        throw new UnsupportedBatchOperation('Fireworks batch result records require a qualified success/error decoder.');
    }

    #[Override]
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        if ($limit < 1 || $limit > 200) {
            throw new InvalidArgumentException('Fireworks batch page size must be between 1 and 200.');
        }
        $cursor?->assertMatches('fireworks', $this->scope(), $limit);
        $query = ['pageSize' => $limit];
        if ($cursor !== null) {
            $query['pageToken'] = $cursor->token();
        }
        $data = $this->http->json('GET', $this->url('/' . $this->resource('batchInferenceJobs') . '?' . http_build_query($query)), $this->headers());
        $entries = $data['batchInferenceJobs'] ?? [];
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new BatchException('Fireworks batch list has no job array.');
        }
        $jobs = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                throw new BatchException('Fireworks batch list contains an invalid job.');
            }
            $jobs[] = $this->job($entry);
        }
        $token = $data['nextPageToken'] ?? null;
        $next = is_string($token) && $token !== '' ? new BatchCursor($token, 'fireworks', $this->scope(), $limit) : null;
        return new BatchJobPage($jobs, $next);
    }

    private function createDataset(string $id, string $name, int $count): void
    {
        try {
            $data = $this->http->json('POST', $this->url('/' . $this->resource('datasets')), $this->headers(), [
                'datasetId' => $id,
                'dataset' => ['userUploaded' => new stdClass(), 'exampleCount' => (string) $count],
            ]);
            if (($data['name'] ?? null) !== $name) {
                throw new BatchException('Fireworks returned a different input dataset name.');
            }
        } catch (BatchHttpException $error) {
            throw new BatchSubmissionException('Fireworks dataset creation failed.', 'create_dataset', $this->certainty($error), ['input_dataset' => $name], $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Fireworks dataset creation acknowledgement is uncertain.', 'create_dataset', BatchMutationCertainty::MayHaveSucceeded, ['input_dataset' => $name], $error);
        }
    }

    private function uploadDataset(string $id, string $path, string $name): void
    {
        try {
            $this->uploader->upload(new BatchFileUpload(
                $this->url('/' . $this->resource('datasets', $id) . ':upload'),
                $path,
                $this->headers(contentType: false),
            ));
        } catch (BatchSubmissionException $error) {
            throw new BatchSubmissionException('Fireworks dataset upload failed.', 'upload', $error->certainty(), ['input_dataset' => $name], $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Fireworks dataset upload acknowledgement is uncertain.', 'upload', BatchMutationCertainty::MayHaveSucceeded, ['input_dataset' => $name], $error);
        }
    }

    /** @return array<string, mixed> */
    private function createJob(string $id, string $name, string $inputDataset, string $outputDataset): array
    {
        $artifacts = ['input_dataset' => $inputDataset, 'output_dataset' => $outputDataset, 'job_name' => $name];
        try {
            $data = $this->http->json('POST', $this->url('/' . $this->resource('batchInferenceJobs') . '?batchInferenceJobId=' . rawurlencode($id)), $this->headers(), [
                'model' => $this->inference->model,
                'inputDatasetId' => $inputDataset,
                'outputDatasetId' => $outputDataset,
            ]);
            if (($data['name'] ?? null) !== $name) {
                throw new BatchException('Fireworks returned a different batch job name.');
            }
            return $data;
        } catch (BatchHttpException $error) {
            throw new BatchSubmissionException('Fireworks batch job creation failed.', 'create', $this->certainty($error), $artifacts, $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Fireworks batch job creation acknowledgement is uncertain.', 'create', BatchMutationCertainty::MayHaveSucceeded, $artifacts, $error);
        }
    }

    /** @param array<string, mixed> $data */
    private function job(array $data, ?BatchReference $reference = null): BatchJob
    {
        $name = $this->requiredName($data);
        if ($reference !== null && $reference->id()->toString() !== $name) {
            throw new BatchException('Fireworks returned a different batch job name.');
        }
        $reference ??= new BatchReference(new BatchJobId($name), 'fireworks', $this->scope(), self::ROUTE, self::CODEC, inputClosed: true);
        $native = is_string($data['state'] ?? null) ? $data['state'] : '';
        $status = $this->status($native);
        $counts = is_array($data['jobProgress'] ?? null) ? $data['jobProgress'] : [];
        $state = is_array($data['status'] ?? null) ? $data['status'] : [];

        return new BatchJob(
            $reference,
            $status,
            $native,
            new BatchProgress(
                total: $this->count($counts['totalInputRequests'] ?? null),
                completed: $this->count($counts['successfullyProcessedRequests'] ?? null),
                failed: $this->count($counts['failedRequests'] ?? null),
            ),
            BatchResultsAvailability::Unsupported,
            new DateTimeImmutable(),
            is_string($state['code'] ?? null) ? $state['code'] : null,
            is_string($state['message'] ?? null) ? $state['message'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    private function requiredName(array $data): string
    {
        $name = $data['name'] ?? null;
        $prefix = $this->resource('batchInferenceJobs') . '/';
        $id = is_string($name) && str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : '';
        if ($id === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._~-]*$/', $id) !== 1) {
            throw new BatchException('Fireworks batch response has no account-scoped job name.');
        }
        return $name;
    }

    private function assertReference(BatchReference $reference): void
    {
        if ($reference->provider() !== 'fireworks' || $reference->scope() !== $this->scope()
            || $reference->route() !== self::ROUTE || $reference->codec() !== self::CODEC) {
            throw new InvalidArgumentException('Batch reference belongs to a different Fireworks connection.');
        }
        $this->requiredName(['name' => $reference->id()->toString()]);
    }

    private function status(string $native): BatchStatus
    {
        return match ($native) {
            'JOB_STATE_CREATING', 'JOB_STATE_VALIDATING', 'JOB_STATE_PENDING', 'JOB_STATE_CREATING_INPUT_DATASET', 'JOB_STATE_RE_QUEUEING', 'JOB_STATE_IDLE', 'JOB_STATE_PAUSED' => BatchStatus::Pending,
            'JOB_STATE_RUNNING' => BatchStatus::Running,
            'JOB_STATE_WRITING_RESULTS' => BatchStatus::Finalizing,
            'JOB_STATE_CANCELLING' => BatchStatus::Cancelling,
            'JOB_STATE_COMPLETED' => BatchStatus::Completed,
            'JOB_STATE_FAILED', 'JOB_STATE_EARLY_STOPPED' => BatchStatus::Failed,
            'JOB_STATE_CANCELLED' => BatchStatus::Cancelled,
            'JOB_STATE_EXPIRED' => BatchStatus::Expired,
            default => BatchStatus::Unknown,
        };
    }

    private function count(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function certainty(BatchHttpException $error): BatchMutationCertainty
    {
        return $error->statusCode() >= 500 ? BatchMutationCertainty::MayHaveSucceeded : BatchMutationCertainty::Rejected;
    }

    private function resource(string $collection, ?string $id = null): string
    {
        $resource = 'accounts/' . $this->settings->accountId() . '/' . $collection;
        return $id === null ? $resource : $resource . '/' . rawurlencode($id);
    }

    private function url(string $path): string
    {
        return $this->settings->apiBaseUrl() . $path;
    }

    /** @return array<string, string> */
    private function headers(bool $contentType = true): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->inference->apiKey];
        if ($contentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }
}
