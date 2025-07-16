<?php declare(strict_types=1);

namespace Cognesy\Schema\Attributes;

use Attribute;

#[Attribute(
    Attribute::TARGET_CLASS
    | Attribute::TARGET_PROPERTY
    | Attribute::TARGET_METHOD
    | Attribute::TARGET_FUNCTION
    | Attribute::TARGET_PARAMETER
    | Attribute::IS_REPEATABLE
)]
class Description
{
    public function __construct(
        public string $text = ''
    ) {}
}
