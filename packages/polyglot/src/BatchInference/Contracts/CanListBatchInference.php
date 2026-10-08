<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Contracts;

use Cognesy\Polyglot\BatchInference\Data\BatchCursor;
use Cognesy\Polyglot\BatchInference\Data\BatchJobPage;

interface CanListBatchInference
{
    public function listJobs(int $limit = 50, ?BatchCursor $cursor = null): BatchJobPage;
}
