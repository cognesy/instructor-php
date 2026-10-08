<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Transport\AwsSdk;

use Aws\Bedrock\BedrockClient;
use Aws\S3\S3Client;
use Cognesy\Polyglot\BatchInference\Contracts\CanOperateBedrockBatch;
use Cognesy\Polyglot\BatchInference\Exceptions\UnsupportedBatchOperation;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final readonly class BedrockBatchSdkTransport implements CanOperateBedrockBatch
{
    public function __construct(private BedrockClient $bedrock, private S3Client $s3)
    {
    }

    public static function forRegion(string $region): self
    {
        if (!class_exists(BedrockClient::class) || !class_exists(S3Client::class)) {
            throw new UnsupportedBatchOperation('AWS Bedrock batch inference requires optional aws/aws-sdk-php.');
        }
        return new self(
            new BedrockClient(['region' => $region, 'version' => 'latest']),
            new S3Client(['region' => $region, 'version' => 'latest']),
        );
    }

    #[\Override]
    public function putInput(string $bucket, string $key, string $path): void
    {
        $body = fopen($path, 'rb');
        if ($body === false) {
            throw new RuntimeException('Could not open Bedrock batch input file.');
        }
        try {
            $this->s3->putObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'Body' => $body,
                'ContentType' => 'application/x-ndjson',
            ]);
        } finally {
            fclose($body);
        }
    }

    /** @param array<string, mixed> $request
     *  @return array<string, mixed>
     */
    #[\Override]
    public function createJob(array $request): array
    {
        return $this->bedrock->createModelInvocationJob($request)->toArray();
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function getJob(string $arn): array
    {
        return $this->bedrock->getModelInvocationJob(['jobIdentifier' => $arn])->toArray();
    }

    #[\Override]
    public function stopJob(string $arn): void
    {
        $this->bedrock->stopModelInvocationJob(['jobIdentifier' => $arn]);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function listJobs(int $limit, ?string $token): array
    {
        $request = ['maxResults' => $limit];
        if ($token !== null) {
            $request['nextToken'] = $token;
        }
        return $this->bedrock->listModelInvocationJobs($request)->toArray();
    }

    /** @return list<string> */
    #[\Override]
    public function outputKeys(string $bucket, string $prefix): array
    {
        $keys = [];
        $token = null;
        do {
            $request = ['Bucket' => $bucket, 'Prefix' => rtrim($prefix, '/').'/'];
            if ($token !== null) {
                $request['ContinuationToken'] = $token;
            }
            $page = $this->s3->listObjectsV2($request)->toArray();
            foreach ($page['Contents'] ?? [] as $object) {
                $key = $object['Key'] ?? null;
                if (is_string($key) && (str_ends_with($key, '.jsonl') || str_ends_with($key, '.jsonl.out'))) {
                    $keys[] = $key;
                }
            }
            $token = is_string($page['NextContinuationToken'] ?? null) && $page['NextContinuationToken'] !== ''
                ? $page['NextContinuationToken']
                : null;
        } while ($token !== null);
        return $keys;
    }

    /** @return iterable<string> */
    #[\Override]
    public function readOutput(string $bucket, string $key): iterable
    {
        $result = $this->s3->getObject(['Bucket' => $bucket, 'Key' => $key]);
        $body = $result['Body'] ?? null;
        if (!$body instanceof StreamInterface) {
            throw new RuntimeException('Bedrock S3 output object has no readable body.');
        }
        try {
            while (true) {
                $chunk = $body->read(65536);
                if ($chunk === '') {
                    if ($body->eof()) {
                        break;
                    }
                    throw new RuntimeException('Bedrock S3 output stream stopped before EOF.');
                }
                yield $chunk;
            }
        } finally {
            $body->close();
        }
    }
}
