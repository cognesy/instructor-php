<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use Cognesy\Retrieval\Query\StoreQuery;

final readonly class StoreCapabilities
{
    /** @param list<class-string<StoreQuery>> $queryTypes */
    public function __construct(
        private array $queryTypes,
        public bool $rankedContinuation = false,
        public bool $scan = false,
        public bool $fetch = false,
        public bool $exact = false,
        public bool $approximate = false,
        public string $ordering = 'strict',
    ) {}

    /** @param class-string<StoreQuery> $queryType */
    public function supports(string $queryType): bool
    {
        return in_array($queryType, $this->queryTypes, true);
    }

    /** @return list<class-string<StoreQuery>> */
    public function queryTypes(): array
    {
        return $this->queryTypes;
    }
}
