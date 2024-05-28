<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OutputField
{
    public function __construct(
        public string $description = '',
    ) {}
}
