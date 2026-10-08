<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Anthropic;

use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Contracts\CanCancelBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanDriveBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanListBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanSendBatchFileBody;
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
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\BatchInference\Preparation\BatchInputPreparer;
use Cognesy\Polyglot\BatchInference\Preparation\BatchJsonArrayFile;
use Cognesy\Polyglot\BatchInference\Preparation\BatchRequestNormalizer;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\BatchInference\Results\JsonlRecordReader;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileBodyRequest;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final readonly class AnthropicBatchDriver implements CanDriveBatchInference, CanCancelBatchInference, CanListBatchInference
{
    public function __construct(
        private BatchConfig $config,
        private BatchHttpTransport $http,
        private CanSendBatchFileBody $bodySender,
    ) {
        if ($config->provider() !== 'anthropic' || $config->codec() !== 'anthropic-messages') {
            throw new InvalidArgumentException('Anthropic batch driver requires an Anthropic batch configuration.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'anthropic';
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
            new BatchInputSupport(BatchInputKind::Inline, 100000, 256000000, 10000000),
        ]);
    }

    #[\Override]
    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        if ($options !== null) {
            throw new InvalidArgumentException('Anthropic batch submission has no provider-specific options.');
        }
        $config = $this->config->inference();
        $jsonl = (new BatchInputPreparer(
            new BatchRequestNormalizer($config->driver, $config->model),
            new AnthropicBatchItemEncoder($config),
            maxItems: 100000,
            maxBytes: 256000000,
        ))->prepare($items);

        try {
            $body = BatchJsonArrayFile::fromJsonl($jsonl);
            try {
                $response = $this->bodySender->send(new BatchFileBodyRequest(
                    $this->url('/messages/batches'),
                    $body->path(),
                    $this->headers(),
                ));
                $data = $this->submittedData($response->body());
                $reference = new BatchReference(
                    id: new BatchJobId($this->requiredId($data)),
                    provider: 'anthropic',
                    scope: $this->scope(),
                    route: '/v1/messages',
                    codec: 'anthropic-messages',
                    expectedCount: $jsonl->count(),
                    inputClosed: true,
                );
                return $this->job($data, $reference);
            } finally {
                $body->close();
            }
        } finally {
            $jsonl->close();
        }
    }

    #[\Override]
    public function retrieve(BatchReference $reference): BatchJob
    {
        $this->assertReference($reference);
        $data = $this->http->json('GET', $this->url('/messages/batches/'.rawurlencode($reference->id()->toString())), $this->headers());
        return $this->job($data, $reference);
    }

    #[\Override]
    public function cancel(BatchReference $reference): BatchCancellation
    {
        $this->assertReference($reference);
        $data = $this->http->json('POST', $this->url('/messages/batches/'.rawurlencode($reference->id()->toString()).'/cancel'), $this->headers());
        $native = is_string($data['processing_status'] ?? null) ? $data['processing_status'] : null;
        return new BatchCancellation($reference, true, $native === 'ended', new DateTimeImmutable(), $native);
    }

    #[\Override]
    public function results(BatchReference $reference): BatchResults
    {
        $this->assertReference($reference);
        if ($reference->codec() !== 'anthropic-messages') {
            throw new BatchException('This batch has no supported Anthropic message result codec.');
        }
        $data = $this->http->json('GET', $this->url('/messages/batches/'.rawurlencode($reference->id()->toString())), $this->headers());
        $job = $this->job($data, $reference);
        $availability = $job->resultsAvailability();
        if (!$availability->isAvailable()) {
            return new BatchResults($availability, [], $availability === BatchResultsAvailability::Unavailable ? 'Anthropic results are not retained or were not produced.' : null);
        }

        return new BatchResults($availability, fn (): iterable => $this->readResults($reference));
    }

    #[\Override]
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        $query = ['limit' => $limit];
        if ($cursor !== null) {
            $cursor->assertMatches('anthropic', $this->scope(), $limit);
            $query['after_id'] = $cursor->token();
        }
        $data = $this->http->json('GET', $this->url('/messages/batches').'?'.http_build_query($query), $this->headers());
        if (!is_array($data['data'] ?? null)) {
            throw new BatchException('Anthropic batch list has no data array.');
        }
        $jobs = [];
        foreach ($data['data'] as $entry) {
            if (!is_array($entry)) {
                throw new BatchException('Anthropic batch list contains an invalid job.');
            }
            $jobs[] = $this->job($entry);
        }
        $next = null;
        if ($data['has_more'] ?? false) {
            $lastId = $data['last_id'] ?? null;
            if (!is_string($lastId) || $lastId === '') {
                throw new BatchException('Anthropic batch list has more pages but no last_id.');
            }
            $next = new BatchCursor($lastId, 'anthropic', $this->scope(), $limit);
        }
        return new BatchJobPage($jobs, $next);
    }

    /** @return array<string, mixed> */
    private function submittedData(string $body): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new BatchSubmissionException('Anthropic batch creation response is not JSON.', 'create', BatchMutationCertainty::MayHaveSucceeded, previous: $error);
        }
        if (!is_array($data) || !is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new BatchSubmissionException('Anthropic batch creation response has no job ID.', 'create', BatchMutationCertainty::MayHaveSucceeded);
        }
        return $data;
    }

    /** @param array<string, mixed> $data */
    private function job(array $data, ?BatchReference $reference = null): BatchJob
    {
        $id = $this->requiredId($data);
        if ($reference !== null && $reference->id()->toString() !== $id) {
            throw new BatchException('Anthropic returned a different batch job ID.');
        }
        $reference ??= new BatchReference(new BatchJobId($id), 'anthropic', $this->scope(), '/v1/messages', 'anthropic-messages', inputClosed: true);
        $native = is_string($data['processing_status'] ?? null) ? $data['processing_status'] : '';
        $status = match ($native) {
            'in_progress' => BatchStatus::Running,
            'canceling' => BatchStatus::Cancelling,
            'ended' => BatchStatus::Completed,
            default => BatchStatus::Unknown,
        };
        $resultsUrl = $data['results_url'] ?? null;
        $availability = match (true) {
            ($data['archived_at'] ?? null) !== null => BatchResultsAvailability::Unavailable,
            $status->isTerminal() && is_string($resultsUrl) && $resultsUrl !== '' => BatchResultsAvailability::Final,
            $status->isTerminal() => BatchResultsAvailability::Unavailable,
            default => BatchResultsAvailability::Pending,
        };
        $counts = is_array($data['request_counts'] ?? null) ? $data['request_counts'] : [];
        $processing = $this->count($counts['processing'] ?? null);
        $completed = $this->count($counts['succeeded'] ?? null);
        $failed = $this->count($counts['errored'] ?? null);
        $cancelled = $this->count($counts['canceled'] ?? null);
        $expired = $this->count($counts['expired'] ?? null);
        $total = $processing !== null && $completed !== null && $failed !== null && $cancelled !== null && $expired !== null
            ? $processing + $completed + $failed + $cancelled + $expired
            : $reference->expectedCount();

        return new BatchJob(
            $reference,
            $status,
            $native,
            new BatchProgress($total, $processing, $completed, $failed, $cancelled, $expired),
            $availability,
            new DateTimeImmutable(),
        );
    }

    /** @return iterable<\Cognesy\Polyglot\BatchInference\Data\BatchItemResult> */
    private function readResults(BatchReference $reference): iterable
    {
        $decoder = new AnthropicBatchRecordDecoder();
        $reader = new JsonlRecordReader();
        $path = '/messages/batches/'.rawurlencode($reference->id()->toString()).'/results';
        foreach ($reader->records($this->http->stream($this->url($path), $this->headers())) as $record) {
            yield $decoder->decode($record, $reference->id()->toString());
        }
    }

    /** @param array<string, mixed> $data */
    private function requiredId(array $data): string
    {
        if (!is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new BatchException('Anthropic batch response has no job ID.');
        }
        return $data['id'];
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
    private function headers(): array
    {
        $config = $this->config->inference();
        $headers = [
            'x-api-key' => $config->apiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => (string) ($config->metadata['apiVersion'] ?? '2023-06-01'),
        ];
        foreach (['beta' => 'anthropic-beta', 'workspace' => 'anthropic-workspace-id'] as $key => $name) {
            $value = $config->metadata[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    private function assertReference(BatchReference $reference): void
    {
        if ($reference->provider() !== 'anthropic' || $reference->scope() !== $this->scope()) {
            throw new InvalidArgumentException('Anthropic batch reference belongs to another connection.');
        }
        if ($reference->route() !== '/v1/messages' || $reference->codec() !== 'anthropic-messages') {
            throw new InvalidArgumentException('Anthropic batch reference has an incompatible endpoint or result codec.');
        }
    }
}
