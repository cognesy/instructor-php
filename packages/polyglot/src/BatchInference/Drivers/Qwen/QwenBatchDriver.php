<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Qwen;

use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Config\QwenBatchOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanCancelBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanDriveBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanListBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanUploadBatchFile;
use Cognesy\Polyglot\BatchInference\Data\BatchCancellation;
use Cognesy\Polyglot\BatchInference\Data\BatchCapabilities;
use Cognesy\Polyglot\BatchInference\Data\BatchInputSupport;
use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchJob;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchJobPage;
use Cognesy\Polyglot\BatchInference\Data\BatchProgress;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Enums\BatchInputKind;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchHttpException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\BatchInference\Preparation\BatchInputPreparer;
use Cognesy\Polyglot\BatchInference\Preparation\BatchRequestNormalizer;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\BatchInference\Results\FileBatchRecordDecoder;
use Cognesy\Polyglot\BatchInference\Results\JsonlRecordReader;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Throwable;

final readonly class QwenBatchDriver implements CanDriveBatchInference, CanCancelBatchInference, CanListBatchInference
{
    public function __construct(
        private BatchConfig $config,
        private BatchHttpTransport $http,
        private CanUploadBatchFile $uploader,
    ) {
        if ($config->provider() !== 'qwen' || $config->codec() !== 'qwen-chat') {
            throw new InvalidArgumentException('Qwen batch driver requires a Model Studio chat batch configuration.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'qwen';
    }
    #[\Override]
    public function scope(): string
    {
        return $this->config->scope();
    }
    #[\Override]
    public function capabilities(): BatchCapabilities
    {
        return new BatchCapabilities(true, true, false, [
            new BatchInputSupport(BatchInputKind::File, 50000, 500000000, 6000000),
        ]);
    }

    #[\Override]
    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        if ($options !== null && !$options instanceof QwenBatchOptions) {
            throw new InvalidArgumentException('Qwen batch submission requires QwenBatchOptions.');
        }
        $options ??= new QwenBatchOptions();
        $input = (new BatchInputPreparer(
            new BatchRequestNormalizer($this->config->inference()->driver, $this->config->inference()->model),
            new QwenBatchItemEncoder($this->config),
            maxItems: 50000,
            maxBytes: 500000000,
            maxRecordBytes: 6000000,
        ))->prepare($items);

        try {
            $uploaded = $this->uploader->upload(new BatchFileUpload(
                url: $this->url('/files'),
                path: $input->path(),
                headers: $this->headers(contentType: false),
                fields: ['purpose' => 'batch'],
            ));
            $inputFileId = $this->uploadedFileId($uploaded->body());
            $data = $this->createJob($inputFileId, $options);
            $reference = new BatchReference(
                id: new BatchJobId($this->requiredId($data)),
                provider: 'qwen',
                scope: $this->scope(),
                route: $this->config->route(),
                codec: $this->config->codec(),
                expectedCount: $input->count(),
                inputClosed: true,
            );
            return $this->job($data, $reference);
        } finally {
            $input->close();
        }
    }

    #[\Override]
    public function retrieve(BatchReference $reference): BatchJob
    {
        $this->assertReference($reference);
        $data = $this->http->json('GET', $this->url('/batches/'.rawurlencode($reference->id()->toString())), $this->headers());
        return $this->job($data, $reference);
    }

    #[\Override]
    public function cancel(BatchReference $reference): BatchCancellation
    {
        $this->assertReference($reference);
        $data = $this->http->json('POST', $this->url('/batches/'.rawurlencode($reference->id()->toString()).'/cancel'), $this->headers());
        $status = $this->status((string) ($data['status'] ?? ''));
        return new BatchCancellation($reference, true, $status->isTerminal(), new DateTimeImmutable(), is_string($data['status'] ?? null) ? $data['status'] : null);
    }

    #[\Override]
    public function results(BatchReference $reference): BatchResults
    {
        $this->assertReference($reference);
        if ($reference->codec() !== 'qwen-chat') {
            throw new BatchException('This batch endpoint has no supported inference result codec.');
        }
        $data = $this->http->json('GET', $this->url('/batches/'.rawurlencode($reference->id()->toString())), $this->headers());
        $job = $this->job($data, $reference);
        $availability = $job->resultsAvailability();
        if (!$availability->isAvailable()) {
            return new BatchResults($availability, [], $availability === BatchResultsAvailability::Unavailable ? 'No retained output or error artifact is available.' : null);
        }

        $artifacts = $this->artifactIds($data);
        return new BatchResults($availability, fn (): iterable => $this->readArtifacts($artifacts, new FileBatchRecordDecoder('qwen-chat')));
    }

    #[\Override]
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Qwen batch listing limit must be between 1 and 100.');
        }
        $query = ['limit' => $limit];
        if ($cursor !== null) {
            $cursor->assertMatches('qwen', $this->scope(), $limit);
            $query['after'] = $cursor->token();
        }
        $data = $this->http->json('GET', $this->url('/batches').'?'.http_build_query($query), $this->headers());
        if (!is_array($data['data'] ?? null)) {
            throw new BatchException('Qwen batch list has no data array.');
        }
        $jobs = [];
        foreach ($data['data'] as $entry) {
            if (!is_array($entry)) {
                throw new BatchException('Qwen batch list contains an invalid job.');
            }
            $jobs[] = $this->job($entry);
        }
        $next = null;
        if ($data['has_more'] ?? false) {
            $lastId = $data['last_id'] ?? null;
            if (!is_string($lastId) || $lastId === '') {
                throw new BatchException('Qwen batch list has more pages but no last_id.');
            }
            $next = new BatchCursor($lastId, 'qwen', $this->scope(), $limit);
        }

        return new BatchJobPage($jobs, $next);
    }

    /** @return array<string, mixed> */
    private function createJob(string $inputFileId, QwenBatchOptions $options): array
    {
        try {
            $body = [
                'input_file_id' => $inputFileId,
                'endpoint' => $this->config->route(),
                'completion_window' => $options->completionWindow(),
            ];
            if ($options->metadata() !== []) {
                $body['metadata'] = $options->metadata();
            }
            $data = $this->http->json('POST', $this->url('/batches'), $this->headers(), $body);
            $this->requiredId($data);
            return $data;
        } catch (BatchHttpException $error) {
            $certainty = $error->statusCode() >= 500 ? BatchMutationCertainty::MayHaveSucceeded : BatchMutationCertainty::Rejected;
            throw new BatchSubmissionException('Qwen batch creation failed.', 'create', $certainty, ['input_file_id' => $inputFileId], $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Qwen batch creation acknowledgement is uncertain.', 'create', BatchMutationCertainty::MayHaveSucceeded, ['input_file_id' => $inputFileId], $error);
        }
    }

    private function uploadedFileId(string $body): string
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new BatchSubmissionException('Qwen file upload response is not JSON.', 'upload', BatchMutationCertainty::MayHaveSucceeded, previous: $error);
        }
        if (!is_array($data) || !is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new BatchSubmissionException('Qwen file upload response has no file ID.', 'upload', BatchMutationCertainty::MayHaveSucceeded);
        }
        return $data['id'];
    }

    /** @param array<string, mixed> $data */
    private function job(array $data, ?BatchReference $reference = null): BatchJob
    {
        $id = $this->requiredId($data);
        if ($reference !== null && $id !== $reference->id()->toString()) {
            throw new BatchException('Qwen returned a different batch job ID.');
        }
        $route = is_string($data['endpoint'] ?? null) ? $data['endpoint'] : ($reference?->route() ?? 'unknown');
        if ($reference !== null && $route !== $reference->route()) {
            throw new BatchException('Qwen returned a different batch endpoint.');
        }
        $codec = match ($route) {
            '/v1/chat/completions' => 'qwen-chat',
            default => 'unsupported',
        };
        $reference ??= new BatchReference(new BatchJobId($id), 'qwen', $this->scope(), $route, $codec, inputClosed: true);
        $nativeStatus = is_string($data['status'] ?? null) ? $data['status'] : '';
        $status = $this->status($nativeStatus);
        $artifacts = $this->artifactIds($data);
        $availability = match (true) {
            $artifacts !== [] && $status->isTerminal() => BatchResultsAvailability::Final,
            $artifacts !== [] => BatchResultsAvailability::Partial,
            $status->isTerminal() => BatchResultsAvailability::Unavailable,
            default => BatchResultsAvailability::Pending,
        };
        $counts = is_array($data['request_counts'] ?? null) ? $data['request_counts'] : [];
        $progress = new BatchProgress(
            total: $this->count($counts['total'] ?? null),
            completed: $this->count($counts['completed'] ?? null),
            failed: $this->count($counts['failed'] ?? null),
        );
        $firstError = is_array($data['errors']['data'][0] ?? null) ? $data['errors']['data'][0] : [];

        return new BatchJob(
            $reference,
            $status,
            $nativeStatus,
            $progress,
            $availability,
            new DateTimeImmutable(),
            is_string($firstError['code'] ?? null) ? $firstError['code'] : null,
            is_string($firstError['message'] ?? null) ? $firstError['message'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    private function requiredId(array $data): string
    {
        if (!is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new BatchException('Qwen batch response has no job ID.');
        }
        return $data['id'];
    }

    private function status(string $native): BatchStatus
    {
        return match ($native) {
            'validating' => BatchStatus::Pending,
            'in_progress' => BatchStatus::Running,
            'finalizing' => BatchStatus::Finalizing,
            'cancelling' => BatchStatus::Cancelling,
            'completed' => BatchStatus::Completed,
            'failed' => BatchStatus::Failed,
            'cancelled' => BatchStatus::Cancelled,
            'expired' => BatchStatus::Expired,
            default => BatchStatus::Unknown,
        };
    }

    /** @param array<string, mixed> $data
     *  @return list<string>
     */
    private function artifactIds(array $data): array
    {
        $ids = [];
        foreach (['output_file_id', 'error_file_id'] as $field) {
            if (is_string($data[$field] ?? null) && $data[$field] !== '') {
                $ids[] = $data[$field];
            }
        }
        return $ids;
    }

    /** @param list<string> $artifacts
     *  @return iterable<\Cognesy\Polyglot\BatchInference\Data\BatchItemResult>
     */
    private function readArtifacts(array $artifacts, FileBatchRecordDecoder $decoder): iterable
    {
        $reader = new JsonlRecordReader();
        foreach ($artifacts as $id) {
            $chunks = $this->http->stream($this->url('/files/'.rawurlencode($id).'/content'), $this->headers());
            foreach ($reader->records($chunks) as $record) {
                yield $decoder->decode($record, $id);
            }
        }
    }

    private function count(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function url(string $path): string
    {
        return rtrim($this->config->apiBaseUrl(), '/').$path;
    }

    /** @return array<string, string> */
    private function headers(bool $contentType = true): array
    {
        $headers = ['Authorization' => 'Bearer '.$this->config->inference()->apiKey];
        if ($contentType) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    private function assertReference(BatchReference $reference): void
    {
        if ($reference->provider() !== 'qwen' || $reference->scope() !== $this->scope()) {
            throw new InvalidArgumentException('Qwen batch reference belongs to another region or connection.');
        }
        $compatible = match ($reference->route()) {
            '/v1/chat/completions' => $reference->codec() === 'qwen-chat',
            default => $reference->codec() === 'unsupported',
        };
        if (!$compatible) {
            throw new InvalidArgumentException('Qwen batch reference has an incompatible endpoint and result codec.');
        }
    }
}
