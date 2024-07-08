<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ModuleSignature
{
    public function __construct(
        public string $signature,
    ) {}
}
