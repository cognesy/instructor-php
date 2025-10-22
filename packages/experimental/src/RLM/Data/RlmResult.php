<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data;

use Cognesy\Experimental\RLM\Data\Handles\ResultHandle;

final readonly class RlmResult
{
    public function __construct(
        public RlmStatus $status,
        public ?ResultHandle $value,
        public Trace $trace,
    ) {}
}

