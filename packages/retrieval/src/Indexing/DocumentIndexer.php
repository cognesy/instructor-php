<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Indexing;

use Cognesy\Retrieval\Indexing\Data\IndexingReport;
use Cognesy\Retrieval\Indexing\Data\TextDocument;

final readonly class DocumentIndexer
{
    public function __construct(
        private DocumentProcessor $processor
    ) {}

    /** @param TextDocument|iterable<TextDocument> $documents */
    public function index(TextDocument|iterable $documents): IndexingReport
    {
        $sources = $documents instanceof TextDocument ? [$documents] : $documents;
        $report = new IndexingReport;
        foreach ($sources as $source) {
            $report = $report->plus($this->processor->process($source));
        }

        return $report;
    }
}
