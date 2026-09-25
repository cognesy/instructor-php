<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Indexing\Contracts;

use Cognesy\Retrieval\Indexing\Data\TextDocument;

interface DocumentFilter
{
    public function accepts(TextDocument $document): bool;
}
