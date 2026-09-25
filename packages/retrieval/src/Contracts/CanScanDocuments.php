<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Contracts;

use Cognesy\Retrieval\Data\DocumentPage;
use Cognesy\Retrieval\Data\ScanRequest;

interface CanScanDocuments
{
    public function scan(ScanRequest $request): DocumentPage;
}
