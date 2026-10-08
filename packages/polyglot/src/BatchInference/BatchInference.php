<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference;

use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchConfig;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Creation\BatchDriverRegistry;
use Cognesy\Polyglot\BatchInference\Data\BatchCancellation;
use Cognesy\Polyglot\BatchInference\Data\BatchCapabilities;
use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchJob;
use Cognesy\Polyglot\BatchInference\Data\BatchJobPage;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

final readonly class BatchInference
{
    public function __construct(private BatchRuntime $runtime)
    {
    }

    public static function fromRuntime(BatchRuntime $runtime): self
    {
        return new self($runtime);
    }

    public static function fromConfig(BatchConfig $config, ?BatchDriverRegistry $drivers = null): self
    {
        return new self(BatchRuntime::fromConfig($config, $drivers));
    }

    public static function using(string $preset, ?string $basePath = null, ?BatchDriverRegistry $drivers = null): self
    {
        return self::fromConfig(BatchConfig::fromLLMConfig(LLMConfig::fromPreset($preset, $basePath)), $drivers);
    }

    public function capabilities(): BatchCapabilities
    {
        return $this->runtime->capabilities();
    }

    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob
    {
        return $this->runtime->submit($items, $options);
    }

    public function retrieve(BatchReference $reference): BatchJob
    {
        return $this->runtime->retrieve($reference);
    }
    public function cancel(BatchReference $reference): BatchCancellation
    {
        return $this->runtime->cancel($reference);
    }
    public function results(BatchReference $reference): BatchResults
    {
        return $this->runtime->results($reference);
    }
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage
    {
        return $this->runtime->listJobs($limit, $cursor);
    }
}
