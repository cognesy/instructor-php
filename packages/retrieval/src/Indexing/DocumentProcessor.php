<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Indexing;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Events\IndexingBatchStored;
use Cognesy\Retrieval\Events\IndexingCompleted;
use Cognesy\Retrieval\Events\IndexingStarted;
use Cognesy\Retrieval\Indexing\Contracts\CanTrackSourceRecords;
use Cognesy\Retrieval\Indexing\Contracts\DocumentFilter;
use Cognesy\Retrieval\Indexing\Contracts\DocumentTransformer;
use Cognesy\Retrieval\Indexing\Data\IndexingReport;
use Cognesy\Retrieval\Indexing\Data\TextDocument;
use Cognesy\Retrieval\Vectorization\Vectorizer;
use InvalidArgumentException;

final readonly class DocumentProcessor
{
    /**
     * @param  list<DocumentFilter>  $filters
     * @param  list<DocumentTransformer>  $transformers
     */
    public function __construct(
        private CanStoreDocuments $store,
        private Vectorizer $vectorizer,
        private CanHandleEvents $events,
        private CanTrackSourceRecords $manifest = new InMemorySourceRecordManifest,
        private array $filters = [],
        private array $transformers = [],
        private int $batchSize = 50,
        private int $batchBytes = 1_000_000,
    ) {
        if ($batchSize < 1 || $batchBytes < 1) {
            throw new InvalidArgumentException('Indexing batch limits must be positive');
        }
    }

    public function process(TextDocument $source): IndexingReport
    {
        $sourceId = $source->sourceIdentity();
        $this->events->dispatch(new IndexingStarted($sourceId));
        $currentIds = [];
        $indexed = 0;
        $batches = 0;
        $batch = [];
        $bytes = 0;
        foreach ($this->prepare($source) as $document) {
            if ($batch !== [] && (count($batch) >= $this->batchSize || $bytes + strlen($document->content) > $this->batchBytes)) {
                $indexed += $this->storeBatch($sourceId, $batch);
                $batches++;
                $batch = [];
                $bytes = 0;
            }
            $batch[] = $document;
            $bytes += strlen($document->content);
            $currentIds[] = $document->id;
        }
        if ($batch !== []) {
            $indexed += $this->storeBatch($sourceId, $batch);
            $batches++;
        }
        $staleIds = array_values(array_diff($this->manifest->recordsFor($sourceId)->all(), $currentIds));
        $removed = $staleIds === [] ? 0 : $this->store->remove(new DocumentIds($staleIds))->acknowledged;
        $this->manifest->replace($sourceId, new DocumentIds($currentIds));
        $this->events->dispatch(new IndexingCompleted($sourceId, $indexed, $removed, $batches));

        return new IndexingReport(1, $indexed, $removed, $batches);
    }

    /** @return iterable<TextDocument> */
    private function prepare(TextDocument $source): iterable
    {
        foreach ($this->filters as $filter) {
            if (! $filter->accepts($source)) {
                return;
            }
        }
        $documents = [$source];
        foreach ($this->transformers as $transformer) {
            $next = [];
            foreach ($documents as $document) {
                foreach ($transformer->transform($document) as $transformed) {
                    $next[] = $transformed;
                }
            }
            $documents = $next;
        }
        yield from $documents;
    }

    /** @param list<TextDocument> $batch */
    private function storeBatch(string $sourceId, array $batch): int
    {
        $result = $this->store->upsert($this->vectorizer->vectorize($batch));
        $this->events->dispatch(new IndexingBatchStored($sourceId, count($batch), $result->acknowledged));

        return $result->acknowledged;
    }
}
