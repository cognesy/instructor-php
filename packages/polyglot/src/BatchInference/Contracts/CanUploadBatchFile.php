<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Contracts;

use Cognesy\Polyglot\BatchInference\Transport\BatchFileUpload;
use Cognesy\Polyglot\BatchInference\Transport\BatchFileUploadResponse;

interface CanUploadBatchFile
{
    public function upload(BatchFileUpload $request): BatchFileUploadResponse;
}
