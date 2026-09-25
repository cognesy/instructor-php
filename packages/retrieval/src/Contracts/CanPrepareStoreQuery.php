<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Contracts;

use Cognesy\Retrieval\Query\StoreQuery;

interface CanPrepareStoreQuery
{
    public function prepare(StoreQuery $query): StoreQuery;
}
