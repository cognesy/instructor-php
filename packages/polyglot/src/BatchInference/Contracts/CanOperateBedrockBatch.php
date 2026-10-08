<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Contracts;

interface CanOperateBedrockBatch
{
    public function putInput(string $bucket, string $key, string $path): void;

    /** @param array<string, mixed> $request
     *  @return array<string, mixed>
     */
    public function createJob(array $request): array;

    /** @return array<string, mixed> */
    public function getJob(string $arn): array;

    public function stopJob(string $arn): void;

    /** @return array<string, mixed> */
    public function listJobs(int $limit, ?string $token): array;

    /** @return list<string> */
    public function outputKeys(string $bucket, string $prefix): array;

    /** @return iterable<string> */
    public function readOutput(string $bucket, string $key): iterable;
}
