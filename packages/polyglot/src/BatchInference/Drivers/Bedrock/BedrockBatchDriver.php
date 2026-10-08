<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Drivers\Bedrock;

use Aws\Exception\AwsException;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Config\BedrockBatchOptions;
use Cognesy\Polyglot\BatchInference\Config\BedrockBatchSettings;
use Cognesy\Polyglot\BatchInference\Contracts\CanCancelBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanDriveBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanListBatchInference;
use Cognesy\Polyglot\BatchInference\Contracts\CanOperateBedrockBatch;
use Cognesy\Polyglot\BatchInference\Data\BatchCancellation;
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
use Cognesy\Polyglot\BatchInference\Exceptions\BatchCancellationException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\BatchInference\Preparation\BatchInputPreparer;
use Cognesy\Polyglot\BatchInference\Preparation\BatchRequestNormalizer;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\BatchInference\Results\JsonlRecordReader;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final readonly class BedrockBatchDriver implements CanDriveBatchInference, CanCancelBatchInference, CanListBatchInference
{
    private const CODEC = 'bedrock-claude3-haiku';

    public function __construct(private BedrockBatchSettings $settings, private CanOperateBedrockBatch $transport)
    {
    }

    #[\Override]
    public function provider(): string
    {
        return 'bedrock';
    }
    #[\Override]
    public function scope(): string
    {
        return $this->settings->scope();
    }
    #[\Override]
    public function capabilities(): BatchCapabilities
    {
        return new BatchCapabilities(true, true, true, [new BatchInputSupport(BatchInputKind::File, 100000, 100000000, 10000000, 100)]);
    }

    #[\Override]
    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        if ($options !== null && !$options instanceof BedrockBatchOptions) {
            throw new InvalidArgumentException('Bedrock submission requires BedrockBatchOptions.');
        }
        $input = (new BatchInputPreparer(
            new BatchRequestNormalizer('anthropic', $this->settings->modelId()),
            new BedrockBatchItemEncoder(),
            maxItems: 100000,
            maxBytes: 100000000,
        ))->prepare($items);
        $name = 'polyglot-'.bin2hex(random_bytes(12));
        $key = $this->settings->inputPrefix().'/'.$name.'.jsonl';
        $inputUri = 's3://'.$this->settings->inputBucket().'/'.$key;
        $outputUri = 's3://'.$this->settings->outputBucket().'/'.$this->settings->outputPrefix().'/'.$name.'/';
        $token = bin2hex(random_bytes(20));

        try {
            if ($input->count() < 100) {
                throw new InvalidArgumentException('Bedrock Claude 3 Haiku batch jobs require at least 100 records.');
            }
            $this->upload($key, $input->path(), $inputUri);
            $data = $this->create($name, $token, $inputUri, $outputUri, $options ?? new BedrockBatchOptions());
            $arn = $data['jobArn'] ?? null;
            if (!is_string($arn) || $arn === '') {
                throw new BatchSubmissionException('Bedrock job creation has no ARN.', 'create', BatchMutationCertainty::MayHaveSucceeded, ['input_s3_uri' => $inputUri, 'job_name' => $name, 'client_request_token' => $token]);
            }
            $reference = new BatchReference(new BatchJobId($arn), 'bedrock', $this->scope(), 'InvokeModel', self::CODEC, $input->count(), true);
            return new BatchJob($reference, BatchStatus::Pending, 'created', new BatchProgress(total: $input->count()), BatchResultsAvailability::Pending, new DateTimeImmutable());
        } finally {
            $input->close();
        }
    }

    #[\Override]
    public function retrieve(BatchReference $reference): BatchJob
    {
        $this->assertReference($reference);
        return $this->job($this->transport->getJob($reference->id()->toString()), $reference);
    }

    #[\Override]
    public function cancel(BatchReference $reference): BatchCancellation
    {
        $this->assertReference($reference);
        try {
            $this->transport->stopJob($reference->id()->toString());
        } catch (AwsException $error) {
            throw new BatchCancellationException($reference, $this->awsCertainty($error), $error);
        }
        return new BatchCancellation($reference, true, false, new DateTimeImmutable(), 'Stopping');
    }

    #[\Override]
    public function results(BatchReference $reference): BatchResults
    {
        $this->assertReference($reference);
        if ($reference->codec() !== self::CODEC) {
            throw new BatchException('This Bedrock job has no supported batch result codec.');
        }
        $data = $this->transport->getJob($reference->id()->toString());
        $job = $this->job($data, $reference);
        $uri = $data['outputDataConfig']['s3OutputDataConfig']['s3Uri'] ?? null;
        if (!is_string($uri) || $uri === '') {
            $availability = $job->isTerminal() ? BatchResultsAvailability::Unavailable : BatchResultsAvailability::Pending;
            return new BatchResults($availability, [], $availability === BatchResultsAvailability::Unavailable ? 'Bedrock published no output location.' : null);
        }
        [$bucket, $prefix] = $this->outputLocation($uri, $data);
        $keys = $this->transport->outputKeys($bucket, $prefix);
        foreach ($keys as $key) {
            if (!str_starts_with($key, $prefix)) {
                throw new BatchException('Bedrock returned an output object outside the job prefix.');
            }
        }
        if ($keys === []) {
            $availability = $job->isTerminal() ? BatchResultsAvailability::Unavailable : BatchResultsAvailability::Pending;
            return new BatchResults($availability, [], $availability === BatchResultsAvailability::Unavailable ? 'No retained Bedrock output objects are available.' : null);
        }
        $availability = $job->status() === BatchStatus::Completed ? BatchResultsAvailability::Final : BatchResultsAvailability::Partial;
        return new BatchResults($availability, fn (): iterable => $this->readOutputs($bucket, $keys));
    }

    #[\Override]
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Bedrock list limit must be between 1 and 1000.');
        }
        $cursor?->assertMatches('bedrock', $this->scope(), $limit);
        $data = $this->transport->listJobs($limit, $cursor?->token());
        $jobs = [];
        foreach ($data['invocationJobSummaries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                throw new BatchException('Bedrock job listing contains an invalid entry.');
            }
            $jobs[] = $this->job($entry);
        }
        $token = $data['nextToken'] ?? null;
        $next = is_string($token) && $token !== '' ? new BatchCursor($token, 'bedrock', $this->scope(), $limit) : null;
        return new BatchJobPage($jobs, $next);
    }

    private function upload(string $key, string $path, string $uri): void
    {
        try {
            $this->transport->putInput($this->settings->inputBucket(), $key, $path);
        } catch (AwsException $error) {
            throw new BatchSubmissionException('Bedrock S3 input upload failed.', 'upload', $this->awsCertainty($error), ['input_s3_uri' => $uri], $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Bedrock S3 input upload acknowledgement is uncertain.', 'upload', BatchMutationCertainty::MayHaveSucceeded, ['input_s3_uri' => $uri], $error);
        }
    }

    /** @return array<string, mixed> */
    private function create(string $name, string $token, string $inputUri, string $outputUri, BedrockBatchOptions $options): array
    {
        try {
            return $this->transport->createJob([
                'jobName' => $name,
                'clientRequestToken' => $token,
                'modelId' => $this->settings->modelId(),
                'modelInvocationType' => 'InvokeModel',
                'roleArn' => $this->settings->roleArn(),
                'inputDataConfig' => ['s3InputDataConfig' => ['s3Uri' => $inputUri]],
                'outputDataConfig' => ['s3OutputDataConfig' => ['s3Uri' => $outputUri]],
                'timeoutDurationInHours' => $options->timeoutHours(),
            ]);
        } catch (AwsException $error) {
            throw new BatchSubmissionException('Bedrock job creation failed.', 'create', $this->awsCertainty($error), ['input_s3_uri' => $inputUri, 'job_name' => $name, 'client_request_token' => $token], $error);
        } catch (Throwable $error) {
            throw new BatchSubmissionException('Bedrock job creation acknowledgement is uncertain.', 'create', BatchMutationCertainty::MayHaveSucceeded, ['input_s3_uri' => $inputUri, 'job_name' => $name, 'client_request_token' => $token], $error);
        }
    }

    /** @param array<string, mixed> $data */
    private function job(array $data, ?BatchReference $reference = null): BatchJob
    {
        $arn = $data['jobArn'] ?? null;
        if (!is_string($arn) || $arn === '') {
            throw new BatchException('Bedrock job response has no ARN.');
        }
        if ($reference !== null && $reference->id()->toString() !== $arn) {
            throw new BatchException('Bedrock returned a different job ARN.');
        }
        $model = $data['modelId'] ?? null;
        $invocation = $data['modelInvocationType'] ?? null;
        $name = $data['jobName'] ?? null;
        $codec = $model === $this->settings->modelId()
            && ($invocation === 'InvokeModel' || $invocation === null)
            && is_string($name)
            && preg_match('/^polyglot-[a-f0-9]{24}$/', $name) === 1
            ? self::CODEC
            : 'unsupported';
        if ($reference !== null && $reference->codec() !== $codec) {
            throw new BatchException('Bedrock returned an incompatible model or invocation type.');
        }
        $reference ??= new BatchReference(new BatchJobId($arn), 'bedrock', $this->scope(), is_string($invocation) ? $invocation : 'unknown', $codec);
        $native = is_string($data['status'] ?? null) ? $data['status'] : '';
        $status = $this->status($native);
        $availability = BatchResultsAvailability::Pending;
        return new BatchJob(
            $reference,
            $status,
            $native,
            new BatchProgress(
                total: is_int($data['totalRecordCount'] ?? null) ? $data['totalRecordCount'] : $reference->expectedCount(),
                completed: is_int($data['successRecordCount'] ?? null) ? $data['successRecordCount'] : null,
                failed: is_int($data['errorRecordCount'] ?? null) ? $data['errorRecordCount'] : null,
            ),
            $availability,
            new DateTimeImmutable(),
            failureMessage: is_string($data['message'] ?? null) ? $data['message'] : null,
        );
    }

    private function status(string $native): BatchStatus
    {
        return match ($native) {
            'Submitted', 'Validating', 'Scheduled' => BatchStatus::Pending,
            'InProgress' => BatchStatus::Running,
            'Stopping' => BatchStatus::Cancelling,
            'Stopped' => BatchStatus::Cancelled,
            'Completed', 'PartiallyCompleted' => BatchStatus::Completed,
            'Failed' => BatchStatus::Failed,
            'Expired' => BatchStatus::Expired,
            default => BatchStatus::Unknown,
        };
    }

    private function awsCertainty(AwsException $error): BatchMutationCertainty
    {
        $status = $error->getStatusCode();
        return match (true) {
            $status !== null && $status >= 400 && $status < 500 && $status !== 408 => BatchMutationCertainty::Rejected,
            default => BatchMutationCertainty::MayHaveSucceeded,
        };
    }

    private function assertReference(BatchReference $reference): void
    {
        if ($reference->provider() !== 'bedrock' || $reference->scope() !== $this->scope()) {
            throw new InvalidArgumentException('Bedrock reference belongs to a different account, region, or S3 scope.');
        }
        if (!in_array($reference->codec(), [self::CODEC, 'unsupported'], true)
            || ($reference->codec() === self::CODEC && $reference->route() !== 'InvokeModel')) {
            throw new InvalidArgumentException('Bedrock reference has an incompatible invocation type or result codec.');
        }
    }

    /** @param array<string, mixed> $job
     *  @return array{string, string}
     */
    private function outputLocation(string $uri, array $job): array
    {
        $parts = parse_url($uri);
        $bucket = is_array($parts) ? ($parts['host'] ?? null) : null;
        $prefix = is_array($parts) ? ltrim((string) ($parts['path'] ?? ''), '/') : '';
        $name = $job['jobName'] ?? null;
        $expected = is_string($name) ? $this->settings->outputPrefix().'/'.$name.'/' : '';
        if ($parts === false || ($parts['scheme'] ?? null) !== 's3' || $bucket !== $this->settings->outputBucket()
            || $prefix !== $expected) {
            throw new BatchException('Bedrock returned an output location outside the configured S3 scope.');
        }
        return [$bucket, $prefix];
    }

    /** @param list<string> $keys
     *  @return iterable<\Cognesy\Polyglot\BatchInference\Data\BatchItemResult>
     */
    private function readOutputs(string $bucket, array $keys): iterable
    {
        $reader = new JsonlRecordReader();
        $decoder = new BedrockBatchRecordDecoder();
        foreach ($keys as $key) {
            foreach ($reader->records($this->transport->readOutput($bucket, $key)) as $record) {
                yield $decoder->decode($record, 's3://'.$bucket.'/'.$key);
            }
        }
    }
}
