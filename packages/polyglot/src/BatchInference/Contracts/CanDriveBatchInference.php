<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Contracts;

use Cognesy\Polyglot\BatchInference\Collections\BatchItems;
use Cognesy\Polyglot\BatchInference\Config\BatchSubmissionOptions;
use Cognesy\Polyglot\BatchInference\Data\BatchCapabilities;
use Cognesy\Polyglot\BatchInference\Data\BatchJob;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;
use Cognesy\Polyglot\BatchInference\Results\BatchResults;

interface CanDriveBatchInference
{
    public function provider(): string;
    public function scope(): string;
    public function capabilities(): BatchCapabilities;
    public function submit(BatchItems $items, ?BatchSubmissionOptions $options = null): BatchJob;
    public function retrieve(BatchReference $reference): BatchJob;
    public function results(BatchReference $reference): BatchResults;
}
