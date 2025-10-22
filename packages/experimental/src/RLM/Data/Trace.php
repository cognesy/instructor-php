<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data;

/**
 * Minimal trace placeholder. Extend with steps/costs when wiring runtime.
 */
final readonly class Trace
{
    public function __construct(
        public string $id = '',
    ) {}
}
