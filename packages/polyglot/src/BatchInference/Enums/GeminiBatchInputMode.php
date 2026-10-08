<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Enums;

enum GeminiBatchInputMode: string
{
    case Inline = 'inline';
    case File = 'file';
}
