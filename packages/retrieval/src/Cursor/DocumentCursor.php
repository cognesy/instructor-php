<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Cursor;

use Cognesy\Retrieval\Contracts\CanScanDocuments;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\StoredDocument;
use IteratorAggregate;
use LogicException;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, StoredDocument> */
final class DocumentCursor implements IteratorAggregate
{
    private bool $started = false;

    private bool $closed = false;

    public function __construct(
        private readonly CanScanDocuments $store,
        private ScanRequest $request,
    ) {}

    #[Override]
    public function getIterator(): Traversable
    {
        if ($this->started) {
            throw new LogicException('Document cursor is forward-only and cannot be rewound');
        }
        $this->started = true;
        while (! $this->closed) {
            $page = $this->store->scan($this->request);
            foreach ($page->documents as $document) {
                yield $document;
                if ($this->closed()) {
                    return;
                }
            }
            if ($page->continuation === null) {
                return;
            }
            $this->request = $this->request->withContinuation($page->continuation);
            unset($page);
        }
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function closed(): bool
    {
        return $this->closed;
    }
}
