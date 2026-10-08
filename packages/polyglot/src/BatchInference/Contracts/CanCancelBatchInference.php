<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Contracts;

use Cognesy\Polyglot\BatchInference\Data\BatchCancellation;
use Cognesy\Polyglot\BatchInference\Data\BatchReference;

interface CanCancelBatchInference
{
    public function cancel(BatchReference $reference): BatchCancellation;
}
