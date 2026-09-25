<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Cursor;

use Closure;
use Cognesy\Retrieval\Data\SearchHit;
use Cognesy\Retrieval\Data\StoreContinuation;
use Cognesy\Retrieval\PendingRetrieval;
use IteratorAggregate;
use LogicException;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, SearchHit> */
final class RetrievalCursor implements IteratorAggregate
{
    private bool $started = false;

    private bool $closed = false;

    /** @param Closure(StoreContinuation): PendingRetrieval $resume */
    public function __construct(
        private PendingRetrieval $pending,
        private readonly Closure $resume,
    ) {}

    #[Override]
    public function getIterator(): Traversable
    {
        if ($this->started) {
            throw new LogicException('Retrieval cursor is forward-only and cannot be rewound');
        }
        $this->started = true;
        while (! $this->closed) {
            $response = $this->pending->response();
            foreach ($response->hits() as $hit) {
                yield $hit;
                if ($this->closed()) {
                    return;
                }
            }
            $continuation = $response->page->continuation;
            if ($continuation === null) {
                return;
            }
            $this->pending = ($this->resume)($continuation);
            unset($response);
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
