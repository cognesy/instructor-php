<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Gemini;

use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Config\GeminiBatchOptions;
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
use Cognesy\Polyglot\BatchInference\Data\BatchManifest;
use Cognesy\Polyglot\BatchInference\Data\BatchProgress;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Enums\BatchInputKind;
use Cognesy\Polyglot\BatchInference\Enums\GeminiBatchInputMode;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchHttpException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\BatchInference\Preparation\BatchInputPreparer;
use Cognesy\Polyglot\BatchInference\Preparation\BatchRequestNormalizer;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\BatchInference\Results\JsonlRecordReader;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileBodyRequest;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;
use Cognesy\Polyglot\BatchInference\Transport\BatchTransportUrl;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final readonly class GeminiBatchDriver implements CanDriveBatchInference, CanCancelBatchInference, CanListBatchInference
{
    public function __construct(
        private BatchConfig $config,
        private BatchHttpTransport $http,
        private CanSendBatchFileBody $bodySender,
    ) {
        if ($config->provider() !== 'gemini' || $config->codec() !== 'gemini-generate-content') {
            throw new InvalidArgumentException('Gemini batch driver requires a native Gemini configuration.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'gemini';
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
            new BatchInputSupport(BatchInputKind::File, 100000, 2000000000, 10000000),
            new BatchInputSupport(BatchInputKind::Inline, 100000, 20000000, 10000000),
        ]);
    }

    #[\Override]
    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        if ($options !== null && !$options instanceof GeminiBatchOptions) {
            throw new InvalidArgumentException('Gemini batch submission requires GeminiBatchOptions.');
        }
        $options ??= new GeminiBatchOptions();
        $encoder = new GeminiBatchItemEncoder($this->config, $options->inputMode());
        $input = (new BatchInputPreparer(
            new BatchRequestNormalizer($this->config->inference()->driver, $this->config->inference()->model),
            $encoder,
            maxItems: 100000,
            maxBytes: $options->inputMode() === GeminiBatchInputMode::Inline ? 20000000 : 2000000000,
        ))->prepare($items);

        try {
            $modelRoute = 'models/'.$encoder->model().':batchGenerateContent';
            $data = match ($options->inputMode()) {
                GeminiBatchInputMode::Inline => $this->createInlineJob($input, $modelRoute, $options),
                GeminiBatchInputMode::File => $this->createFileJob($input, $modelRoute, $options),
            };
            $reference = new BatchReference(
                id: new BatchJobId($this->requiredName($data)),
                provider: 'gemini',
                scope: $this->scope(),
                route: $modelRoute,
                codec: 'gemini-generate-content',
                expectedCount: $input->count(),
                inputClosed: true,
                manifest: $encoder->manifest(),
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
        return $this->job($this->http->json('GET', $this->url($reference->id()->toString()), $this->headers()), $reference);
    }

    #[\Override]
    public function cancel(BatchReference $reference): BatchCancellation
    {
        $this->assertReference($reference);
        $this->http->ack('POST', $this->url($reference->id()->toString().':cancel'), $this->headers());
        return new BatchCancellation($reference, true, false, new DateTimeImmutable());
    }

    #[\Override]
    public function results(BatchReference $reference): BatchResults
    {
        $this->assertReference($reference);
        if ($reference->codec() !== 'gemini-generate-content') {
            throw new BatchException('This Gemini batch has no supported inference result codec.');
        }
        $data = $this->http->json('GET', $this->url($reference->id()->toString()), $this->headers());
        $job = $this->job($data, $reference);
        $availability = $job->resultsAvailability();
        if (!$availability->isAvailable()) {
            return new BatchResults($availability, [], $availability === BatchResultsAvailability::Unavailable ? 'Gemini published no retained results.' : null);
        }

        $source = $this->output($data);
        $inline = $this->inlineRecords($source);
        if ($inline !== null && $inline !== []) {
            return new BatchResults($availability, fn (): iterable => $this->decodeRecords($inline, 'inline', $reference->manifest()));
        }
        $fileName = $source['responsesFile'] ?? null;
        if (!is_string($fileName)) {
            throw new BatchException('Gemini result source is neither inline responses nor a file.');
        }
        $this->assertFileName($fileName);
        return new BatchResults($availability, fn (): iterable => $this->readFile($fileName, $reference->manifest()));
    }

    #[\Override]
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        $query = ['pageSize' => $limit];
        if ($cursor !== null) {
            $cursor->assertMatches('gemini', $this->scope(), $limit);
            $query['pageToken'] = $cursor->token();
        }
        $data = $this->http->json('GET', $this->url('batches').'?'.http_build_query($query), $this->headers());
        $operations = $data['operations'] ?? [];
        if (!is_array($operations)) {
            throw new BatchException('Gemini batch list has no operations array.');
        }
        $jobs = [];
        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                throw new BatchException('Gemini batch list contains an invalid operation.');
            }
            $jobs[] = $this->job($operation);
        }
        $token = $data['nextPageToken'] ?? null;
        $next = is_string($token) && $token !== '' ? new BatchCursor($token, 'gemini', $this->scope(), $limit) : null;
        return new BatchJobPage($jobs, $next);
    }

    /** @return array<string, mixed> */
    private function createInlineJob(\Cognesy\Polyglot\BatchInference\Preparation\PreparedBatchInput $input, string $route, GeminiBatchOptions $options): array
    {
        $body = GeminiInlineBodyFile::fromJsonl($input, $options->displayName());
        try {
            if ($body->bytes() > 20000000) {
                throw new InvalidArgumentException('Gemini inline batch body exceeds 20 MB.');
            }
            $response = $this->bodySender->send(new BatchFileBodyRequest(
                $this->url($route),
                $body->path(),
                $this->headers(),
                'POST',
            ));
            return $this->createdOperation($response->body(), []);
        } finally {
            $body->close();
        }
    }

    /** @return array<string, mixed> */
    private function createFileJob(\Cognesy\Polyglot\BatchInference\Preparation\PreparedBatchInput $input, string $route, GeminiBatchOptions $options): array
    {
        $fileName = $this->uploadFile($input);
        try {
            $data = $this->http->json('POST', $this->url($route), $this->headers(), [
                'batch' => [
                    'displayName' => $options->displayName(),
                    'inputConfig' => ['fileName' => $fileName],
                ],
            ]);
            $this->requiredName($data);
            return $data;
        } catch (BatchHttpException $error) {
            $certainty = $error->statusCode() >= 500 ? BatchMutationCertainty::MayHaveSucceeded : BatchMutationCertainty::Rejected;
            throw new BatchSubmissionException('Gemini batch creation failed.', 'create', $certainty, ['file_name' => $fileName], $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Gemini batch creation acknowledgement is uncertain.', 'create', BatchMutationCertainty::MayHaveSucceeded, ['file_name' => $fileName], $error);
        }
    }

    private function uploadFile(\Cognesy\Polyglot\BatchInference\Preparation\PreparedBatchInput $input): string
    {
        try {
            $start = $this->http->response('POST', 'https://generativelanguage.googleapis.com/upload/v1beta/files', [
                'x-goog-api-key' => $this->config->inference()->apiKey,
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $input->bytes(),
                'X-Goog-Upload-Header-Content-Type' => 'application/jsonl',
                'Content-Type' => 'application/json',
            ], '{"file":{"displayName":"polyglot-batch-input"}}');
            $uploadUrl = $this->uploadUrl($start->headers());
            $uploaded = $this->bodySender->send(new BatchFileBodyRequest($uploadUrl, $input->path(), [
                'Content-Length' => (string) $input->bytes(),
                'Content-Type' => 'application/jsonl',
                'X-Goog-Upload-Offset' => '0',
                'X-Goog-Upload-Command' => 'upload, finalize',
            ], 'POST'));
            $data = json_decode($uploaded->body(), true, 512, JSON_THROW_ON_ERROR);
            $fileName = is_array($data) && is_array($data['file'] ?? null) ? ($data['file']['name'] ?? null) : null;
            if (!is_string($fileName)) {
                throw new BatchException('Gemini upload acknowledgement has no file name.');
            }
            $this->assertFileName($fileName);
            return $fileName;
        } catch (BatchSubmissionException $error) {
            throw $error;
        } catch (BatchHttpException $error) {
            $certainty = $error->statusCode() >= 500 ? BatchMutationCertainty::MayHaveSucceeded : BatchMutationCertainty::Rejected;
            throw new BatchSubmissionException('Gemini upload failed.', 'upload', $certainty, previous: $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Gemini upload acknowledgement is uncertain.', 'upload', BatchMutationCertainty::MayHaveSucceeded, previous: $error);
        }
    }

    /** @param array<string, mixed> $headers */
    private function uploadUrl(array $headers): string
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) !== 'x-goog-upload-url') {
                continue;
            }
            $url = is_array($value) ? ($value[0] ?? null) : $value;
            if (!is_string($url) || parse_url($url, PHP_URL_HOST) !== 'generativelanguage.googleapis.com') {
                break;
            }
            BatchTransportUrl::assertSecure($url);
            return $url;
        }
        throw new BatchException('Gemini resumable upload did not return a trusted upload URL.');
    }

    /** @param array<string, mixed> $data */
    private function job(array $data, ?BatchReference $reference = null): BatchJob
    {
        $name = $this->requiredName($data);
        if ($reference !== null && $name !== $reference->id()->toString()) {
            throw new BatchException('Gemini returned a different batch job name.');
        }
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $model = is_string($metadata['model'] ?? null) ? $metadata['model'] : null;
        $route = $model !== null ? $model.':batchGenerateContent' : ($reference?->route() ?? 'unknown');
        if ($reference !== null && $reference->route() !== 'unknown' && $route !== $reference->route()) {
            throw new BatchException('Gemini returned a different batch model route.');
        }
        $reference ??= new BatchReference(new BatchJobId($name), 'gemini', $this->scope(), $route, 'gemini-generate-content', inputClosed: true);
        $nativeStatus = is_string($metadata['state'] ?? null) ? $metadata['state'] : '';
        $status = $this->status($nativeStatus, $data);
        $source = $this->output($data);
        $inline = $this->inlineRecords($source);
        $available = ($inline !== null && $inline !== [])
            || (is_string($source['responsesFile'] ?? null) && $source['responsesFile'] !== '');
        $availability = match (true) {
            $available && $status->isTerminal() => BatchResultsAvailability::Final,
            $available => BatchResultsAvailability::Partial,
            $status->isTerminal() => BatchResultsAvailability::Unavailable,
            default => BatchResultsAvailability::Pending,
        };
        $stats = is_array($metadata['batchStats'] ?? null) ? $metadata['batchStats'] : [];
        $progress = new BatchProgress(
            total: $this->count($stats['requestCount'] ?? null),
            completed: $this->count($stats['successfulRequestCount'] ?? null),
            failed: $this->count($stats['failedRequestCount'] ?? null),
        );
        $error = is_array($data['error'] ?? null) ? $data['error'] : [];
        return new BatchJob(
            $reference,
            $status,
            $nativeStatus,
            $progress,
            $availability,
            new DateTimeImmutable(),
            isset($error['code']) ? (string) $error['code'] : null,
            is_string($error['message'] ?? null) ? $error['message'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    private function status(string $native, array $data): BatchStatus
    {
        return match ($native) {
            'JOB_STATE_PENDING', 'BATCH_STATE_PENDING' => BatchStatus::Pending,
            'JOB_STATE_RUNNING', 'BATCH_STATE_RUNNING' => BatchStatus::Running,
            'JOB_STATE_SUCCEEDED', 'BATCH_STATE_SUCCEEDED' => BatchStatus::Completed,
            'JOB_STATE_FAILED', 'BATCH_STATE_FAILED' => BatchStatus::Failed,
            'JOB_STATE_CANCELLED', 'BATCH_STATE_CANCELLED' => BatchStatus::Cancelled,
            'JOB_STATE_EXPIRED', 'BATCH_STATE_EXPIRED' => BatchStatus::Expired,
            default => match (true) {
                ($data['done'] ?? false) !== true => BatchStatus::Unknown,
                ($data['error']['code'] ?? null) === 1 => BatchStatus::Cancelled,
                isset($data['error']) => BatchStatus::Failed,
                default => BatchStatus::Completed,
            },
        };
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function output(array $data): array
    {
        if (is_array($data['response'] ?? null)) {
            return $data['response'];
        }
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        return is_array($metadata['output'] ?? null) ? $metadata['output'] : [];
    }

    /** @param array<string, mixed> $source
     *  @return ?list<array<string, mixed>>
     */
    private function inlineRecords(array $source): ?array
    {
        $inline = $source['inlinedResponses'] ?? null;
        if (is_array($inline) && is_array($inline['inlinedResponses'] ?? null)) {
            $inline = $inline['inlinedResponses'];
        }
        if (!is_array($inline) || !array_is_list($inline)) {
            return null;
        }
        foreach ($inline as $record) {
            if (!is_array($record)) {
                throw new BatchException('Gemini inline result contains an invalid record.');
            }
        }
        return $inline;
    }

    /** @return iterable<\Cognesy\Polyglot\BatchInference\Data\BatchItemResult> */
    private function readFile(string $fileName, ?BatchManifest $manifest): iterable
    {
        $url = 'https://generativelanguage.googleapis.com/download/v1beta/'.$fileName.':download?alt=media';
        $records = (new JsonlRecordReader())->records($this->http->stream($url, $this->headers()));
        yield from $this->decodeRecords($records, $fileName, $manifest);
    }

    /** @param iterable<array<string, mixed>> $records
     *  @return iterable<\Cognesy\Polyglot\BatchInference\Data\BatchItemResult>
     */
    private function decodeRecords(iterable $records, string $artifactId, ?BatchManifest $manifest): iterable
    {
        $decoder = new GeminiBatchRecordDecoder();
        $ordinal = 0;
        $usedManifest = false;
        foreach ($records as $record) {
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            if (!is_string($record['key'] ?? $metadata['key'] ?? null)) {
                $usedManifest = true;
            }
            yield $decoder->decode($record, $artifactId, $ordinal, $manifest);
            $ordinal++;
        }
        if ($usedManifest && $manifest !== null && $ordinal !== $manifest->count()) {
            throw new BatchException('Gemini ordinal result count differs from the saved manifest.');
        }
    }

    /** @param array<string, mixed> $data */
    private function requiredName(array $data): string
    {
        $name = $data['name'] ?? null;
        if (!is_string($name) || !preg_match('~^batches/[A-Za-z0-9._-]+$~', $name)) {
            throw new BatchException('Gemini batch response has no valid operation name.');
        }
        return $name;
    }

    /** @param array<string, string> $artifactIds
     *  @return array<string, mixed>
     */
    private function createdOperation(string $body, array $artifactIds): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new BatchException('Gemini batch creation returned no operation.');
            }
            $this->requiredName($data);
            return $data;
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Gemini batch creation acknowledgement is uncertain.', 'create', BatchMutationCertainty::MayHaveSucceeded, $artifactIds, $error);
        }
    }

    private function count(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        return is_int($parsed) ? $parsed : null;
    }

    private function assertFileName(string $fileName): void
    {
        if (!preg_match('~^files/[A-Za-z0-9._-]+$~', $fileName)) {
            throw new BatchException('Gemini file resource name is invalid.');
        }
    }

    private function assertReference(BatchReference $reference): void
    {
        if ($reference->provider() !== 'gemini' || $reference->scope() !== $this->scope()) {
            throw new InvalidArgumentException('Gemini batch reference belongs to another connection.');
        }
        $this->requiredName(['name' => $reference->id()->toString()]);
        if ($reference->codec() !== 'gemini-generate-content') {
            throw new InvalidArgumentException('Gemini batch reference has an incompatible result codec.');
        }
        if ($reference->route() !== 'unknown'
            && !preg_match('~^models/[A-Za-z0-9._-]+:batchGenerateContent$~', $reference->route())) {
            throw new InvalidArgumentException('Gemini batch reference has an invalid model route.');
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->config->apiBaseUrl(), '/').'/'.$path;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'x-goog-api-key' => $this->config->inference()->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
