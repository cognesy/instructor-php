<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class InputField
{
    public function __construct(
        public string $description = '',
    ) {}
}
