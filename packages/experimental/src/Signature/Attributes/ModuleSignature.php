<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ModuleSignature
{
    public function __construct(
        public string $signature,
    ) {}
}
