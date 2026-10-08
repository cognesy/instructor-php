<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use InvalidArgumentException;

final readonly class BedrockBatchSettings
{
    public const MODEL_ID = 'anthropic.claude-3-haiku-20240307-v1:0';

    private string $accountId;

    public function __construct(
        private string $region,
        private string $roleArn,
        private string $inputBucket,
        private string $outputBucket,
        private string $inputPrefix = 'polyglot-batch/input',
        private string $outputPrefix = 'polyglot-batch/output',
    ) {
        if (preg_match('/^[a-z]{2}-[a-z]+-\d+$/', $region) !== 1) {
            throw new InvalidArgumentException('A valid AWS region is required for Bedrock batch inference.');
        }
        if (preg_match('/^arn:aws:iam::(?<account>\d{12}):role\/.+$/', $roleArn, $match) !== 1) {
            throw new InvalidArgumentException('A Bedrock batch service role ARN with account ID is required.');
        }
        foreach ([$inputBucket, $outputBucket] as $bucket) {
            if (preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $bucket) !== 1) {
                throw new InvalidArgumentException('Valid S3 input and output bucket names are required.');
            }
        }
        foreach ([$inputPrefix, $outputPrefix] as $prefix) {
            if ($prefix === '' || str_starts_with($prefix, '/') || str_contains($prefix, '..')) {
                throw new InvalidArgumentException('Bedrock S3 prefixes must be nonempty relative paths.');
            }
        }
        $this->accountId = $match['account'];
    }

    public function region(): string
    {
        return $this->region;
    }
    public function roleArn(): string
    {
        return $this->roleArn;
    }
    public function inputBucket(): string
    {
        return $this->inputBucket;
    }
    public function outputBucket(): string
    {
        return $this->outputBucket;
    }
    public function inputPrefix(): string
    {
        return trim($this->inputPrefix, '/');
    }
    public function outputPrefix(): string
    {
        return trim($this->outputPrefix, '/');
    }
    public function modelId(): string
    {
        return self::MODEL_ID;
    }
    public function scope(): string
    {
        return implode('|', ['bedrock', $this->region, $this->accountId, $this->inputBucket, $this->outputBucket]);
    }
}
