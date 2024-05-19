<?php
namespace Cognesy\Instructor\Schema\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::TARGET_PROPERTY)]
class Description
{
    public function __construct(
        public string $text = ''
    ) {}
}
