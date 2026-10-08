<?php

declare(strict_types=1);

use Cognesy\Messages\Messages;
use Aws\Command;
use Aws\Exception\AwsException;
use Cognesy\Polyglot\BatchInference\BatchInference;
use Cognesy\Polyglot\BatchInference\BatchRuntime;
use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BedrockBatchOptions;
use Cognesy\Polyglot\BatchInference\Config\BedrockBatchSettings;
use Cognesy\Polyglot\BatchInference\Contracts\CanOperateBedrockBatch;
use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use Cognesy\Polyglot\BatchInference\Data\BatchJobId;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Drivers\Bedrock\BedrockBatchDriver;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Cognesy\Polyglot\BatchInference\Enums\BatchMutationCertainty;
use Cognesy\Polyglot\BatchInference\Enums\BatchStatus;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchCancellationException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchSubmissionException;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use GuzzleHttp\Psr7\Response;

final class ScriptedBedrockBatchTransport implements CanOperateBedrockBatch
{
    public const ARN = 'arn:aws:bedrock:us-east-1:123456789012:model-invocation-job/123456789012';

    /** @var list<array{bucket: string, key: string, body: string}> */
    public array $uploads = [];
    /** @var array<string, mixed> */
    public array $createRequest = [];
    public int $stopCalls = 0;
    public int $getCalls = 0;
    /** @var list<string> */
    public array $outputObjectKeys = [];
    /** @var array<string, string> */
    public array $outputBodies = [];
    public string $status = 'Submitted';
    public ?Throwable $stopError = null;
    public ?Throwable $uploadError = null;
    public ?Throwable $createError = null;

    public function putInput(string $bucket, string $key, string $path): void
    {
        if ($this->uploadError !== null) {
            throw $this->uploadError;
        }
        $this->uploads[] = ['bucket' => $bucket, 'key' => $key, 'body' => (string) file_get_contents($path)];
    }
    public function createJob(array $request): array
    {
        if ($this->createError !== null) {
            throw $this->createError;
        }
        $this->createRequest = $request;
        return ['jobArn' => self::ARN];
    }
    public function getJob(string $arn): array
    {
        $this->getCalls++;
        return [
            'jobArn' => $arn,
            'jobName' => $this->createRequest['jobName'],
            'modelId' => BedrockBatchSettings::MODEL_ID,
            'modelInvocationType' => 'InvokeModel',
            'status' => $this->status,
            'totalRecordCount' => 100,
            'successRecordCount' => 99,
            'errorRecordCount' => 1,
            'outputDataConfig' => $this->createRequest['outputDataConfig'] ?? null,
        ];
    }
    public function stopJob(string $arn): void
    {
        $this->stopCalls++;
        if ($this->stopError !== null) {
            throw $this->stopError;
        }
    }
    public function listJobs(int $limit, ?string $token): array
    {
        return ['invocationJobSummaries' => [$this->getJob(self::ARN)], 'nextToken' => $token === null ? 'next' : null];
    }
    public function outputKeys(string $bucket, string $prefix): array
    {
        return $this->outputObjectKeys;
    }
    public function readOutput(string $bucket, string $key): iterable
    {
        yield from str_split($this->outputBodies[$key] ?? '', 7);
    }
}

function bedrockTestFacade(ScriptedBedrockBatchTransport $transport): BatchInference
{
    $settings = new BedrockBatchSettings(
        region: 'us-east-1',
        roleArn: 'arn:aws:iam::123456789012:role/BatchInference',
        inputBucket: 'batch-input-example',
        outputBucket: 'batch-output-example',
    );
    return BatchInference::fromRuntime(new BatchRuntime(new BedrockBatchDriver($settings, $transport)));
}

function bedrockTestItems(): BatchItems
{
    return BatchItems::fromIterable((static function (): Generator {
        for ($index = 1; $index <= 100; $index++) {
            yield BatchItem::of('row-'.$index, new InferenceRequest(messages: Messages::fromString('Reply ready.')));
        }
    })());
}

it('runs the isolated Bedrock S3 and job lifecycle with multiple keyed output objects', function () {
    $transport = new ScriptedBedrockBatchTransport();
    $batches = bedrockTestFacade($transport);
    $items = bedrockTestItems();

    $submitted = $batches->submit($items, new BedrockBatchOptions(48));
    $reference = BatchReference::fromArray($submitted->reference()->toArray());
    $input = array_map(static fn (string $row): array => json_decode($row, true, flags: JSON_THROW_ON_ERROR), array_filter(explode("\n", $transport->uploads[0]['body'])));
    expect($submitted->status())->toBe(BatchStatus::Pending)
        ->and($reference->id()->toString())->toBe(ScriptedBedrockBatchTransport::ARN)
        ->and($input[0]['recordId'])->toBe('row-1')
        ->and($input[0]['modelInput']['anthropic_version'])->toBe('bedrock-2023-05-31')
        ->and($input[0]['modelInput'])->not->toHaveKey('model')
        ->and($transport->createRequest['timeoutDurationInHours'])->toBe(48)
        ->and($transport->createRequest['inputDataConfig']['s3InputDataConfig']['s3Uri'])->toStartWith('s3://batch-input-example/');

    $transport->status = 'Completed';
    $prefix = 'polyglot-batch/output/'.$transport->createRequest['jobName'].'/';
    $transport->outputObjectKeys = [$prefix.'a.jsonl.out', $prefix.'b.jsonl.out'];
    $transport->outputBodies = array_fill_keys($transport->outputObjectKeys, '');
    foreach ($input as $index => $row) {
        $record = $index === 1
            ? ['recordId' => $row['recordId'], 'error' => ['errorCode' => 400, 'errorMessage' => 'bad request']]
            : [
                'recordId' => $row['recordId'],
                'modelInput' => $row['modelInput'],
                'modelOutput' => ['id' => 'msg_'.$index, 'type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'ready']], 'model' => BedrockBatchSettings::MODEL_ID, 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 5, 'output_tokens' => 1]],
            ];
        $transport->outputBodies[$transport->outputObjectKeys[intdiv($index, 50)]] .= json_encode($record, JSON_THROW_ON_ERROR)."\n";
    }
    $retrieved = $batches->retrieve($reference);
    $results = iterator_to_array($batches->results($reference)->items());
    expect($retrieved->status())->toBe(BatchStatus::Completed)
        ->and($retrieved->resultsAvailability())->toBe(BatchResultsAvailability::Pending)
        ->and(count($results))->toBe(100)
        ->and($results[0]->key())->toBe('row-1')
        ->and($results[0]->result()->isSuccess())->toBeTrue()
        ->and($results[0]->provenance()->artifactId())->toStartWith('s3://batch-output-example/')
        ->and($results[1]->key())->toBe('row-2')
        ->and($results[1]->result()->isFailure())->toBeTrue()
        ->and($results[99]->key())->toBe('row-100');

    $page = $batches->listJobs(2);
    expect(count(iterator_to_array($page->jobs())))->toBe(1)
        ->and($page->nextCursor())->not->toBeNull()
        ->and($batches->cancel($reference)->acknowledged())->toBeTrue()
        ->and($transport->stopCalls)->toBe(1);
});

it('binds Bedrock references and S3 output locations to the configured account and buckets', function () {
    $transport = new ScriptedBedrockBatchTransport();
    $batches = bedrockTestFacade($transport);
    $job = $batches->submit(bedrockTestItems());
    $saved = $job->reference()->toArray();
    $saved['scope'] = 'bedrock|eu-west-1|123456789012|other|other';
    expect(fn () => $batches->retrieve(BatchReference::fromArray($saved)))->toThrow(InvalidArgumentException::class);

    $transport->status = 'Completed';
    $transport->createRequest['outputDataConfig']['s3OutputDataConfig']['s3Uri'] = 's3://other-bucket/output/';
    expect(fn () => $batches->results($job->reference()))->toThrow(RuntimeException::class);
});

it('rejects incompatible Bedrock invocation references before AWS transport calls', function () {
    $transport = new ScriptedBedrockBatchTransport();
    $batches = bedrockTestFacade($transport);
    $job = $batches->submit(bedrockTestItems());
    $wrongRoute = $job->reference()->toArray();
    $wrongRoute['route'] = 'Converse';
    $wrongCodec = $job->reference()->toArray();
    $wrongCodec['codec'] = 'openai-chat';

    expect(fn () => $batches->retrieve(BatchReference::fromArray($wrongRoute)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $batches->cancel(BatchReference::fromArray($wrongRoute)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $batches->results(BatchReference::fromArray($wrongCodec)))->toThrow(InvalidArgumentException::class)
        ->and($transport->getCalls)->toBe(0)
        ->and($transport->stopCalls)->toBe(0);
});

it('rejects Bedrock infrastructure configuration before any provider I/O', function () {
    expect(fn () => new BedrockBatchSettings('not-a-region', 'arn:aws:iam::123456789012:role/Batch', 'batch-input-example', 'batch-output-example'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new BedrockBatchSettings('us-east-1', '', 'batch-input-example', 'batch-output-example'))->toThrow(InvalidArgumentException::class);
});

it('rejects a below-quota Bedrock batch before S3 upload', function () {
    $transport = new ScriptedBedrockBatchTransport();
    $batches = bedrockTestFacade($transport);
    expect(fn () => $batches->submit(BatchItems::of(BatchItem::of('one', new InferenceRequest(messages: Messages::fromString('One.'))))))
        ->toThrow(InvalidArgumentException::class)
        ->and($transport->uploads)->toBe([])
        ->and($batches->capabilities()->inputModes()[0]->enforcedMinItems())->toBe(100);
});

it('distinguishes a rejected Bedrock stop from an uncertain acknowledgement', function () {
    $settings = new BedrockBatchSettings(
        region: 'us-east-1',
        roleArn: 'arn:aws:iam::123456789012:role/BatchInference',
        inputBucket: 'batch-input-example',
        outputBucket: 'batch-output-example',
    );
    $reference = new BatchReference(new BatchJobId(ScriptedBedrockBatchTransport::ARN), 'bedrock', $settings->scope(), 'InvokeModel', 'bedrock-claude3-haiku', 100, true);

    foreach ([
        [400, BatchMutationCertainty::Rejected],
        [408, BatchMutationCertainty::MayHaveSucceeded],
        [null, BatchMutationCertainty::MayHaveSucceeded],
    ] as [$status, $certainty]) {
        $transport = new ScriptedBedrockBatchTransport();
        $context = match ($status) {
            null => ['connection_error' => true],
            default => ['response' => new Response($status)],
        };
        $transport->stopError = new AwsException('Bedrock stop failed.', new Command('StopModelInvocationJob'), $context);
        $batches = BatchInference::fromRuntime(new BatchRuntime(new BedrockBatchDriver($settings, $transport)));

        try {
            $batches->cancel($reference);
            test()->fail('Expected a Bedrock cancellation error.');
        } catch (BatchCancellationException $error) {
            expect($error->reference())->toBe($reference)
                ->and($error->certainty())->toBe($certainty)
                ->and($error->getPrevious())->toBe($transport->stopError)
                ->and($transport->stopCalls)->toBe(1)
                ->and($transport->getCalls)->toBe(0);
        }
    }
});

it('distinguishes rejected Bedrock upload and creation from uncertain mutations', function () {
    foreach ([
        ['upload', 403, BatchMutationCertainty::Rejected],
        ['upload', 408, BatchMutationCertainty::MayHaveSucceeded],
        ['upload', null, BatchMutationCertainty::MayHaveSucceeded],
        ['create', 400, BatchMutationCertainty::Rejected],
        ['create', 408, BatchMutationCertainty::MayHaveSucceeded],
        ['create', null, BatchMutationCertainty::MayHaveSucceeded],
    ] as [$stage, $status, $certainty]) {
        $transport = new ScriptedBedrockBatchTransport();
        $context = match ($status) {
            null => ['connection_error' => true],
            default => ['response' => new Response($status)],
        };
        $command = match ($stage) {
            'upload' => 'PutObject',
            default => 'CreateModelInvocationJob',
        };
        $awsError = new AwsException('Bedrock mutation failed.', new Command($command), $context);
        if ($stage === 'upload') {
            $transport->uploadError = $awsError;
        } else {
            $transport->createError = $awsError;
        }
        $batches = bedrockTestFacade($transport);

        try {
            $batches->submit(bedrockTestItems());
            test()->fail('Expected a Bedrock submission error.');
        } catch (BatchSubmissionException $error) {
            expect($error->stage())->toBe($stage)
                ->and($error->certainty())->toBe($certainty)
                ->and($error->getPrevious())->toBe($awsError)
                ->and($error->artifactIds())->toHaveKey('input_s3_uri')
                ->and(count($transport->uploads))->toBe(match ($stage) {
                    'upload' => 0,
                    default => 1,
                });
        }
    }
});

it('reports unavailable results when a terminal Bedrock job has no output location', function () {
    $transport = new ScriptedBedrockBatchTransport();
    $batches = bedrockTestFacade($transport);
    $job = $batches->submit(bedrockTestItems());
    $transport->status = 'Completed';
    unset($transport->createRequest['outputDataConfig']);

    expect($batches->results($job->reference())->availability())->toBe(BatchResultsAvailability::Unavailable);
});
