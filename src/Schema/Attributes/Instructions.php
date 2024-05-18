<?php
namespace Cognesy\Instructor\Schema\Attributes;

use Attribute;

#[Attribute]
class Instructions
{
    public function __construct(
        public string $text = ''
    ) {}
}
