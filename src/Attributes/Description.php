<?php
namespace Cognesy\Instructor\Attributes;

use Attribute;

#[Attribute]
class Description {
    public function __construct(
        public string $text = ''
    ) {}
}
