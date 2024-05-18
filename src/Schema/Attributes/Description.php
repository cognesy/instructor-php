<?php
namespace Cognesy\Instructor\Schema\Attributes;

use Attribute;

#[Attribute]
class Description
{
    public function __construct(
        public string $text = ''
    ) {}
}
