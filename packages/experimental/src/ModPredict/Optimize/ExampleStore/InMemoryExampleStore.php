<?php

namespace Cognesy\Experimental\ModPredict\Optimize\ExampleStore;

use Cognesy\Experimental\ModPredict\Optimize\Contracts\ExampleStore;
use Cognesy\Experimental\ModPredict\Optimize\Data\ObservationRecord;

final class InMemoryExampleStore implements ExampleStore
{
    /** @var ObservationRecord[] */
    private array $records = [];

    public function add(ObservationRecord $record): void {
        $this->records[] = $record;
    }

    public function find(string $signatureId, array $filters = []): iterable {
        foreach ($this->records as $r) {
            if ($r->signatureId === $signatureId) {
                yield $r;
            }
        }
    }
}