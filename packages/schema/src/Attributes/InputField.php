<?php declare(strict_types=1);

namespace Cognesy\Schema\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class InputField extends SignatureField
{
}
