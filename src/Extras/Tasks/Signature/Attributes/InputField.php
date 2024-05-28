<?php
namespace Cognesy\Instructor\Extras\Tasks\Signature\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class InputField
{
    public function __construct(
        public string $description = '',
    ) {}
}
