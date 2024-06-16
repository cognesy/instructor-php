<?php

namespace Cognesy\Instructor\Extras\Module\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Signature
{
    public function __construct(
        public string $signature,
    ) {}
}
