<?php

declare(strict_types=1);

namespace Cognesy\Xprompt\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AsPrompt
{
    public function __construct(
        public readonly string $name,
    ) {}
}
