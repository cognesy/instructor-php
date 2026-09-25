<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Indexing\Contracts;

use Cognesy\Retrieval\Indexing\Data\TextDocument;

interface DocumentTransformer
{
    /** @return iterable<TextDocument> */
    public function transform(TextDocument $document): iterable;
}
