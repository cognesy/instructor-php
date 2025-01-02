<?php
namespace Cognesy\Instructor\Experimental\Module\Signature\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ModuleDescription
{
    public function __construct(
        public string $text,
    ) {}
}
