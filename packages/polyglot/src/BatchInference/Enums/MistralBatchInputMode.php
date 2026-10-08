<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Enums;

enum MistralBatchInputMode: string
{
    case File = 'file';
    case Inline = 'inline';
}
