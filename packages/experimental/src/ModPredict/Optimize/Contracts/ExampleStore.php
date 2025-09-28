<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Contracts;

use Cognesy\Experimental\ModPredict\Optimize\Data\ObservationRecord;

interface ExampleStore
{
    public function add(ObservationRecord $record): void;

    /** @return iterable<ObservationRecord> */
    public function find(string $signatureId, array $filters = []): iterable;
}

