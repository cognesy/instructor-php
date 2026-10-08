<?php

declare(strict_types=1);

use Aws\Bedrock\BedrockClient;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Cognesy\Polyglot\BatchInference\Transport\AwsSdk\BedrockBatchSdkTransport;
use GuzzleHttp\Psr7\Utils;

it('uses AWS SDK commands for job control and S3 objects without forwarding HTTP credentials', function () {
    $bedrockMock = new MockHandler([
        new Result(['jobArn' => 'arn:aws:bedrock:us-east-1:123456789012:model-invocation-job/123456789012']),
        new Result(['jobArn' => 'arn:aws:bedrock:us-east-1:123456789012:model-invocation-job/123456789012', 'status' => 'Completed']),
        new Result([]),
        new Result(['invocationJobSummaries' => [], 'nextToken' => 'next']),
    ]);
    $s3Mock = new MockHandler([
        new Result([]),
        new Result(['Contents' => [['Key' => 'out/a.jsonl.out'], ['Key' => 'out/manifest.json.out']], 'NextContinuationToken' => 'page-2']),
        new Result(['Contents' => [['Key' => 'out/b.jsonl.out']]]),
        new Result(['Body' => Utils::streamFor("{\"recordId\":\"a\"}\n")]),
    ]);
    $config = ['region' => 'us-east-1', 'version' => 'latest', 'credentials' => ['key' => 'test', 'secret' => 'test']];
    $transport = new BedrockBatchSdkTransport(
        new BedrockClient([...$config, 'handler' => $bedrockMock]),
        new S3Client([...$config, 'handler' => $s3Mock]),
    );
    $path = tempnam(sys_get_temp_dir(), 'bedrock-sdk-input-');
    file_put_contents($path, "{\"recordId\":\"a\"}\n");
    try {
        $transport->putInput('input-bucket', 'input/a.jsonl', $path);
        $created = $transport->createJob(['jobName' => 'probe', 'modelId' => 'anthropic.claude-3-haiku-20240307-v1:0', 'roleArn' => 'arn:aws:iam::123456789012:role/Batch', 'inputDataConfig' => ['s3InputDataConfig' => ['s3Uri' => 's3://input-bucket/input/a.jsonl']], 'outputDataConfig' => ['s3OutputDataConfig' => ['s3Uri' => 's3://output-bucket/out/']]]);
        $arn = $created['jobArn'];
        $job = $transport->getJob($arn);
        $transport->stopJob($arn);
        $page = $transport->listJobs(2, null);
        $keys = $transport->outputKeys('output-bucket', 'out');
        $chunks = iterator_to_array($transport->readOutput('output-bucket', $keys[0]));
        expect($job['status'])->toBe('Completed')
            ->and($page['nextToken'])->toBe('next')
            ->and($keys)->toBe(['out/a.jsonl.out', 'out/b.jsonl.out'])
            ->and(implode('', $chunks))->toBe("{\"recordId\":\"a\"}\n")
            ->and($bedrockMock->getLastCommand()->getName())->toBe('ListModelInvocationJobs')
            ->and($s3Mock->getLastCommand()->getName())->toBe('GetObject');
    } finally {
        unlink($path);
    }
});
