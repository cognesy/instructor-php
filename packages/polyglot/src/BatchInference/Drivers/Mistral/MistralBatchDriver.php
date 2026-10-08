<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Mistral;

use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Config\MistralBatchOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanCancelBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanDriveBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanListBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanSendBatchFileBody;
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
use Cognesy\Polyglot\BatchInference\Results\FileBatchRecordDecoder;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Enums\BatchInputKind;
use Cognesy\Polyglot\BatchInference\Enums\MistralBatchInputMode;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchHttpException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\BatchInference\Preparation\BatchInputPreparer;
use Cognesy\Polyglot\BatchInference\Preparation\BatchJsonArrayFile;
use Cognesy\Polyglot\BatchInference\Preparation\BatchRequestNormalizer;
use Cognesy\Polyglot\BatchInference\Preparation\PreparedBatchInput;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\BatchInference\Results\JsonlRecordReader;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileBodyRequest;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Throwable;

final readonly class MistralBatchDriver implements CanDriveBatchInference, CanCancelBatchInference, CanListBatchInference
{
    public function __construct(
        private BatchConfig $config,
        private BatchHttpTransport $http,
        private CanUploadBatchFile $uploader,
        private CanSendBatchFileBody $bodySender,
    ) {
        if ($config->provider() !== 'mistral' || $config->codec() !== 'mistral-chat') {
            throw new InvalidArgumentException('Mistral batch driver requires a Mistral chat configuration.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'mistral';
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
            new BatchInputSupport(BatchInputKind::File, 1000000, 200000000, 10000000),
            new BatchInputSupport(BatchInputKind::Inline, 9999, 200000000, 10000000),
        ]);
    }

    #[\Override]
    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        if ($options !== null && !$options instanceof MistralBatchOptions) {
            throw new InvalidArgumentException('Mistral batch submission requires MistralBatchOptions.');
        }
        $options ??= new MistralBatchOptions();
        $config = $this->config->inference();
        $encoder = new MistralBatchItemEncoder($config);
        $input = (new BatchInputPreparer(
            new BatchRequestNormalizer($config->driver, $config->model),
            $encoder,
            maxItems: $options->inputMode() === MistralBatchInputMode::Inline ? 9999 : 1000000,
            maxBytes: 200000000,
        ))->prepare($items);

        try {
            $data = match ($options->inputMode()) {
                MistralBatchInputMode::File => $this->createFileJob($input, $encoder->model(), $options),
                MistralBatchInputMode::Inline => $this->createInlineJob($input, $encoder->model(), $options),
            };
            $reference = new BatchReference(
                new BatchJobId($this->requiredId($data)),
                'mistral',
                $this->scope(),
                '/v1/chat/completions',
                $options->inputMode() === MistralBatchInputMode::Inline ? 'mistral-chat-inline' : 'mistral-chat-file',
                $input->count(),
                true,
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
        $data = $this->http->json('GET', $this->url('/batch/jobs/'.rawurlencode($reference->id()->toString())), $this->headers());
        return $this->job($data, $reference);
    }

    #[\Override]
    public function cancel(BatchReference $reference): BatchCancellation
    {
        $this->assertReference($reference);
        $data = $this->http->json('POST', $this->url('/batch/jobs/'.rawurlencode($reference->id()->toString()).'/cancel'), $this->headers());
        $native = is_string($data['status'] ?? null) ? $data['status'] : null;
        return new BatchCancellation($reference, true, $native !== null && $this->status($native)->isTerminal(), new DateTimeImmutable(), $native);
    }

    #[\Override]
    public function results(BatchReference $reference): BatchResults
    {
        $this->assertReference($reference);
        if (!in_array($reference->codec(), ['mistral-chat-file', 'mistral-chat-inline'], true)) {
            throw new BatchException('This Mistral job has no supported chat result codec.');
        }
        $path = '/batch/jobs/'.rawurlencode($reference->id()->toString());
        if ($reference->codec() === 'mistral-chat-inline') {
            $path .= '?inline=true';
        }
        $data = $this->http->json('GET', $this->url($path), $this->headers());
        $job = $this->job($data, $reference);
        $availability = $job->resultsAvailability();
        if (!$availability->isAvailable()) {
            return new BatchResults($availability, [], $availability === BatchResultsAvailability::Unavailable ? 'No Mistral result artifact is available.' : null);
        }

        return new BatchResults($availability, fn (): iterable => $this->readResults($data));
    }

    #[\Override]
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        $page = 0;
        if ($cursor !== null) {
            $cursor->assertMatches('mistral', $this->scope(), $limit);
            if (!ctype_digit($cursor->token())) {
                throw new InvalidArgumentException('Invalid Mistral batch page cursor.');
            }
            $page = (int) $cursor->token();
        }
        $data = $this->http->json('GET', $this->url('/batch/jobs').'?'.http_build_query(['page' => $page, 'page_size' => $limit]), $this->headers());
        if (!is_array($data['data'] ?? null) || !is_int($data['total'] ?? null)) {
            throw new BatchException('Mistral batch list has no data array or total count.');
        }
        $jobs = [];
        foreach ($data['data'] as $entry) {
            if (!is_array($entry)) {
                throw new BatchException('Mistral batch list contains an invalid job.');
            }
            $jobs[] = $this->job($entry);
        }
        $next = ($page + 1) * $limit < $data['total']
            ? new BatchCursor((string) ($page + 1), 'mistral', $this->scope(), $limit)
            : null;
        return new BatchJobPage($jobs, $next);
    }

    /** @return array<string, mixed> */
    private function createFileJob(PreparedBatchInput $input, string $model, MistralBatchOptions $options): array
    {
        $uploaded = $this->uploader->upload(new BatchFileUpload(
            $this->url('/files'),
            $input->path(),
            $this->headers(contentType: false),
            ['purpose' => 'batch'],
        ));
        try {
            $file = json_decode($uploaded->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new BatchSubmissionException('Mistral file upload response is not JSON.', 'upload', BatchMutationCertainty::MayHaveSucceeded, previous: $error);
        }
        if (!is_array($file) || !is_string($file['id'] ?? null) || $file['id'] === '') {
            throw new BatchSubmissionException('Mistral file upload response has no ID.', 'upload', BatchMutationCertainty::MayHaveSucceeded);
        }
        $inputFileId = $file['id'];
        try {
            $data = $this->http->json('POST', $this->url('/batch/jobs'), $this->headers(), [
                'input_files' => [$inputFileId],
                'model' => $model,
                'endpoint' => '/v1/chat/completions',
                'timeout_hours' => $options->timeoutHours(),
            ]);
            $this->requiredId($data);
            return $data;
        } catch (BatchHttpException $error) {
            $certainty = $error->statusCode() >= 500 ? BatchMutationCertainty::MayHaveSucceeded : BatchMutationCertainty::Rejected;
            throw new BatchSubmissionException('Mistral batch creation failed.', 'create', $certainty, ['input_file_id' => $inputFileId], $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Mistral batch creation acknowledgement is uncertain.', 'create', BatchMutationCertainty::MayHaveSucceeded, ['input_file_id' => $inputFileId], $error);
        }
    }

    /** @return array<string, mixed> */
    private function createInlineJob(PreparedBatchInput $input, string $model, MistralBatchOptions $options): array
    {
        $body = BatchJsonArrayFile::fromJsonl($input, 'requests', [
            'model' => $model,
            'endpoint' => '/v1/chat/completions',
            'timeout_hours' => $options->timeoutHours(),
        ]);
        try {
            $response = $this->bodySender->send(new BatchFileBodyRequest($this->url('/batch/jobs'), $body->path(), $this->headers()));
            try {
                $data = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new BatchSubmissionException('Mistral inline batch response is not JSON.', 'create', BatchMutationCertainty::MayHaveSucceeded, previous: $error);
            }
            if (!is_array($data) || !is_string($data['id'] ?? null) || $data['id'] === '') {
                throw new BatchSubmissionException('Mistral inline batch response has no job ID.', 'create', BatchMutationCertainty::MayHaveSucceeded);
            }
            return $data;
        } finally {
            $body->close();
        }
    }

    /** @param array<string, mixed> $data */
    private function job(array $data, ?BatchReference $reference = null): BatchJob
    {
        $id = $this->requiredId($data);
        if ($reference !== null && $reference->id()->toString() !== $id) {
            throw new BatchException('Mistral returned a different batch job ID.');
        }
        $route = is_string($data['endpoint'] ?? null) ? $data['endpoint'] : ($reference?->route() ?? 'unknown');
        if ($reference !== null && $reference->route() !== 'unknown' && $route !== $reference->route()) {
            throw new BatchException('Mistral returned a different batch endpoint.');
        }
        $codec = match (true) {
            $route !== '/v1/chat/completions' => 'unsupported',
            is_array($data['input_files'] ?? null) && $data['input_files'] !== [] => 'mistral-chat-file',
            default => 'mistral-chat-inline',
        };
        $reference ??= new BatchReference(new BatchJobId($id), 'mistral', $this->scope(), $route, $codec, inputClosed: true);
        $native = is_string($data['status'] ?? null) ? $data['status'] : '';
        $status = $this->status($native);
        $artifacts = $this->artifactIds($data);
        $inlineOutputs = is_array($data['outputs'] ?? null) && $data['outputs'] !== [];
        $availability = match (true) {
            ($artifacts !== [] || $inlineOutputs) && $status->isTerminal() => BatchResultsAvailability::Final,
            $artifacts !== [] || $inlineOutputs => BatchResultsAvailability::Partial,
            $status->isTerminal() => BatchResultsAvailability::Unavailable,
            default => BatchResultsAvailability::Pending,
        };

        return new BatchJob(
            $reference,
            $status,
            $native,
            new BatchProgress(
                total: $this->count($data['total_requests'] ?? null),
                completed: $this->count($data['succeeded_requests'] ?? null),
                failed: $this->count($data['failed_requests'] ?? null),
            ),
            $availability,
            new DateTimeImmutable(),
        );
    }

    /** @param array<string, mixed> $data
     *  @return iterable<\Cognesy\Polyglot\BatchInference\Data\BatchItemResult>
     */
    private function readResults(array $data): iterable
    {
        $decoder = new FileBatchRecordDecoder('openai-chat');
        $inline = is_array($data['outputs'] ?? null) ? $data['outputs'] : [];
        $seen = [];
        foreach ($inline as $record) {
            if (!is_array($record)) {
                throw new BatchException('Mistral inline batch output contains an invalid record.');
            }
            $item = $decoder->decode($record, 'inline');
            if ($item->key() !== null) {
                $seen[$item->key()] = true;
            }
            yield $item;
        }
        $reader = new JsonlRecordReader();
        $artifacts = $inline === [] ? $this->artifactIds($data) : [$data['error_file'] ?? null];
        foreach ($artifacts as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            $chunks = $this->http->stream($this->url('/files/'.rawurlencode($id).'/content'), $this->headers());
            foreach ($reader->records($chunks) as $record) {
                $item = $decoder->decode($record, $id);
                if ($item->key() !== null && isset($seen[$item->key()])) {
                    continue;
                }
                yield $item;
            }
        }
    }

    /** @param array<string, mixed> $data
     *  @return list<string>
     */
    private function artifactIds(array $data): array
    {
        $ids = [];
        foreach (['output_file', 'error_file'] as $field) {
            if (is_string($data[$field] ?? null) && $data[$field] !== '') {
                $ids[] = $data[$field];
            }
        }
        return $ids;
    }

    /** @param array<string, mixed> $data */
    private function requiredId(array $data): string
    {
        if (!is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new BatchException('Mistral batch response has no job ID.');
        }
        return $data['id'];
    }

    private function status(string $native): BatchStatus
    {
        return match ($native) {
            'QUEUED' => BatchStatus::Pending,
            'RUNNING' => BatchStatus::Running,
            'CANCELLATION_REQUESTED' => BatchStatus::Cancelling,
            'SUCCESS' => BatchStatus::Completed,
            'FAILED' => BatchStatus::Failed,
            'TIMEOUT_EXCEEDED' => BatchStatus::Expired,
            'CANCELLED' => BatchStatus::Cancelled,
            default => BatchStatus::Unknown,
        };
    }

    private function count(mixed $value): ?int
    {
        return match (true) {
            is_int($value) && $value >= 0 => $value,
            is_string($value) && ctype_digit($value) => (int) $value,
            default => null,
        };
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
        if ($reference->provider() !== 'mistral' || $reference->scope() !== $this->scope()) {
            throw new InvalidArgumentException('Mistral batch reference belongs to another connection.');
        }
        $compatible = match ($reference->route()) {
            '/v1/chat/completions' => in_array($reference->codec(), ['mistral-chat-file', 'mistral-chat-inline'], true),
            default => $reference->codec() === 'unsupported',
        };
        if (!$compatible) {
            throw new InvalidArgumentException('Mistral batch reference has an incompatible endpoint and result codec.');
        }
    }
}
