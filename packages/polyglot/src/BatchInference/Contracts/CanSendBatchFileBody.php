<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Contracts;

use Cognesy\Polyglot\BatchInference\Transport\BatchFileBodyRequest;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;

interface CanSendBatchFileBody
{
    public function send(BatchFileBodyRequest $request): BatchFileUploadResponse;
}
