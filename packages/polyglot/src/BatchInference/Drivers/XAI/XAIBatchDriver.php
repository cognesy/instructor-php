<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\XAI;

use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Config\XAIBatchOptions;
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
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use Throwable;

final readonly class XAIBatchDriver implements CanDriveBatchInference, CanCancelBatchInference, CanListBatchInference
{
    public function __construct(
        private BatchConfig $config,
        private BatchHttpTransport $http,
        private CanUploadBatchFile $uploader,
    ) {
        if ($config->provider() !== 'xai' || $config->codec() !== 'xai-chat') {
            throw new InvalidArgumentException('xAI batch driver requires an xAI chat batch configuration.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'xai';
    }
    #[\Override]
    public function scope(): string
    {
        return $this->config->scope();
    }
    #[\Override]
    public function capabilities(): BatchCapabilities
    {
        return new BatchCapabilities(true, true, true, [
            new BatchInputSupport(BatchInputKind::File, 50000, 200000000, 25000000),
        ]);
    }

    #[\Override]
    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        if ($options !== null && !$options instanceof XAIBatchOptions) {
            throw new InvalidArgumentException('xAI batch submission requires XAIBatchOptions.');
        }
        $options ??= new XAIBatchOptions();
        $input = (new BatchInputPreparer(
            new BatchRequestNormalizer($this->config->inference()->driver, $this->config->inference()->model),
            new XAIBatchItemEncoder($this->config),
            maxItems: 50000,
            maxBytes: 200000000,
            maxRecordBytes: 25000000,
        ))->prepare($items);

        try {
            $uploaded = $this->uploader->upload(new BatchFileUpload(
                $this->url('/files'),
                $input->path(),
                $this->headers(contentType: false),
            ));
            $fileId = $this->uploadedFileId($uploaded->body());
            $data = $this->createJob($fileId, $options);
            $reference = new BatchReference(
                new BatchJobId($this->requiredId($data)),
                'xai',
                $this->scope(),
                $this->config->route(),
                $this->config->codec(),
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
        $data = $this->http->json('GET', $this->url('/batches/'.rawurlencode($reference->id()->toString())), $this->headers());
        return $this->job($data, $reference);
    }

    #[\Override]
    public function cancel(BatchReference $reference): BatchCancellation
    {
        $this->assertReference($reference);
        $data = $this->http->json('POST', $this->url('/batches/'.rawurlencode($reference->id()->toString()).':cancel'), $this->headers());
        $job = $this->job($data, $reference);
        return new BatchCancellation($reference, true, $job->isTerminal(), new DateTimeImmutable(), $job->providerStatus());
    }

    #[\Override]
    public function results(BatchReference $reference): BatchResults
    {
        $this->assertReference($reference);
        if ($reference->codec() !== 'xai-chat') {
            throw new BatchException('This xAI batch endpoint has no supported inference result codec.');
        }
        $job = $this->retrieve($reference);
        $availability = $job->resultsAvailability();
        if (!$availability->isAvailable()) {
            return new BatchResults($availability, []);
        }
        return new BatchResults($availability, fn (): iterable => $this->readResultPages($reference));
    }

    #[\Override]
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('xAI batch listing limit must be between 1 and 1000.');
        }
        $query = ['limit' => $limit];
        if ($cursor !== null) {
            $cursor->assertMatches('xai', $this->scope(), $limit);
            $query['pagination_token'] = $cursor->token();
        }
        $data = $this->http->json('GET', $this->url('/batches').'?'.http_build_query($query), $this->headers());
        $entries = $data['batches'] ?? null;
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new BatchException('xAI batch list has no batches array.');
        }
        $jobs = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                throw new BatchException('xAI batch list contains an invalid job.');
            }
            $jobs[] = $this->job($entry);
        }
        $token = $data['pagination_token'] ?? null;
        $next = is_string($token) && $token !== '' ? new BatchCursor($token, 'xai', $this->scope(), $limit) : null;
        return new BatchJobPage($jobs, $next);
    }

    /** @return array<string, mixed> */
    private function createJob(string $fileId, XAIBatchOptions $options): array
    {
        try {
            $data = $this->http->json('POST', $this->url('/batches'), $this->headers(), [
                'name' => $options->name(),
                'input_file_id' => $fileId,
            ]);
            $this->requiredId($data);
            return $data;
        } catch (BatchHttpException $error) {
            $certainty = $error->statusCode() >= 500 ? BatchMutationCertainty::MayHaveSucceeded : BatchMutationCertainty::Rejected;
            throw new BatchSubmissionException('xAI batch creation failed.', 'create', $certainty, ['input_file_id' => $fileId], $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('xAI batch creation acknowledgement is uncertain.', 'create', BatchMutationCertainty::MayHaveSucceeded, ['input_file_id' => $fileId], $error);
        }
    }

    private function uploadedFileId(string $body): string
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new BatchSubmissionException('xAI file upload response is not JSON.', 'upload', BatchMutationCertainty::MayHaveSucceeded, previous: $error);
        }
        $id = is_array($data) ? ($data['id'] ?? null) : null;
        if (!is_string($id) || $id === '') {
            throw new BatchSubmissionException('xAI file upload response has no file ID.', 'upload', BatchMutationCertainty::MayHaveSucceeded);
        }
        return $id;
    }

    /** @param array<string, mixed> $data */
    private function job(array $data, ?BatchReference $reference = null): BatchJob
    {
        $id = $this->requiredId($data);
        if ($reference !== null && $id !== $reference->id()->toString()) {
            throw new BatchException('xAI returned a different batch job ID.');
        }
        $reference ??= new BatchReference(new BatchJobId($id), 'xai', $this->scope(), $this->config->route(), 'xai-chat');
        $state = is_array($data['state'] ?? null) ? $data['state'] : [];
        $total = $this->count($state['num_requests'] ?? null);
        $pending = $this->count($state['num_pending'] ?? null);
        $success = $this->count($state['num_success'] ?? null);
        $errors = $this->count($state['num_error'] ?? null);
        $cancelled = $this->count($state['num_cancelled'] ?? null);
        $observedAt = new DateTimeImmutable();
        $status = $this->status($data, $reference, $total, $pending, $success, $errors, $cancelled, $observedAt);
        $processed = ($success ?? 0) + ($errors ?? 0) + ($cancelled ?? 0);
        $availability = match (true) {
            $this->resultsExpired($data, $observedAt) => BatchResultsAvailability::Unavailable,
            $status->isTerminal() => BatchResultsAvailability::Final,
            $processed > 0 => BatchResultsAvailability::Partial,
            default => BatchResultsAvailability::Pending,
        };
        $message = is_string($data['cancel_by_xai_message'] ?? null) ? $data['cancel_by_xai_message'] : null;

        return new BatchJob(
            $reference,
            $status,
            $status->value,
            new BatchProgress(total: $total, processing: $pending, completed: $success, failed: $errors, cancelled: $cancelled),
            $availability,
            $observedAt,
            $message !== null ? 'xai_cancelled' : null,
            $message,
        );
    }

    /** @param array<string, mixed> $data */
    private function resultsExpired(array $data, DateTimeImmutable $observedAt): bool
    {
        $raw = $data['expire_time'] ?? $data['expires_at'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return false;
        }
        $expiry = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'))
            ?: DateTimeImmutable::createFromFormat(DATE_RFC3339_EXTENDED, $raw)
            ?: DateTimeImmutable::createFromFormat(DATE_ATOM, $raw);
        return $expiry !== false && $expiry <= $observedAt;
    }

    /** @param array<string, mixed> $data */
    private function status(array $data, BatchReference $reference, ?int $total, ?int $pending, ?int $success, ?int $errors, ?int $cancelled, DateTimeImmutable $observedAt): BatchStatus
    {
        $closed = $reference->inputClosed();
        $expected = $reference->expectedCount();
        $processed = ($success ?? 0) + ($errors ?? 0) + ($cancelled ?? 0);
        $cancelTime = $data['cancel_time'] ?? null;
        if (is_string($cancelTime) && $cancelTime !== '') {
            return $pending === 0 ? BatchStatus::Cancelled : BatchStatus::Cancelling;
        }
        if ($closed && $expected !== null && $expected > 0 && $total !== null && $total >= $expected
            && $pending === 0 && $processed >= $expected) {
            return BatchStatus::Completed;
        }
        if ($this->resultsExpired($data, $observedAt)) {
            return BatchStatus::Expired;
        }
        if (($total ?? 0) > 0 || ($pending ?? 0) > 0) {
            return BatchStatus::Running;
        }
        return $closed ? BatchStatus::Pending : BatchStatus::Unknown;
    }

    /** @return iterable<\Cognesy\Polyglot\BatchInference\Data\BatchItemResult> */
    private function readResultPages(BatchReference $reference): iterable
    {
        $decoder = new XAIBatchRecordDecoder();
        $seenTokens = [];
        $token = null;
        do {
            $query = ['limit' => 100];
            if ($token !== null) {
                $query['pagination_token'] = $token;
            }
            $url = $this->url('/batches/'.rawurlencode($reference->id()->toString()).'/results').'?'.http_build_query($query);
            $data = $this->http->json('GET', $url, $this->headers());
            $records = $data['results'] ?? null;
            if (!is_array($records) || !array_is_list($records)) {
                throw new BatchException('xAI batch result page has no results array.');
            }
            foreach ($records as $record) {
                if (!is_array($record)) {
                    throw new BatchException('xAI batch result page contains an invalid result.');
                }
                yield $decoder->decode($record);
            }
            $next = $data['pagination_token'] ?? null;
            if (!is_string($next) || $next === '') {
                return;
            }
            if (isset($seenTokens[$next])) {
                throw new BatchException('xAI batch result pagination repeated a token.');
            }
            $seenTokens[$next] = true;
            $token = $next;
        } while (true);
    }

    /** @param array<string, mixed> $data */
    private function requiredId(array $data): string
    {
        $id = $data['batch_id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new BatchException('xAI batch response has no batch ID.');
        }
        return $id;
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
        if ($reference->provider() !== 'xai' || $reference->scope() !== $this->scope()) {
            throw new InvalidArgumentException('xAI batch reference belongs to another connection.');
        }
        if ($reference->route() !== '/v1/chat/completions' || $reference->codec() !== 'xai-chat') {
            throw new InvalidArgumentException('xAI batch reference has an incompatible endpoint or result codec.');
        }
    }
}
