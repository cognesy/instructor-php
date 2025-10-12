<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream;

use Iterator;
use IteratorAggregate;

/**
 * @template TKey
 * @template TValue
 * @extends IteratorAggregate<TKey, TValue>
 */
interface Stream extends IteratorAggregate
{
    #[\Override]
    public function getIterator(): Iterator;
}

