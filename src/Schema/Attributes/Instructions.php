<?php
namespace Cognesy\Instructor\Schema\Attributes;

use Attribute;

#[Attribute(
    Attribute::TARGET_CLASS
    |Attribute::TARGET_PROPERTY
    |Attribute::TARGET_METHOD
    |Attribute::TARGET_FUNCTION
    |Attribute::TARGET_PARAMETER
)]
class Instructions
{
    public function __construct(
        public string $text = ''
    ) {}
}
