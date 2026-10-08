<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Enums;

enum BatchInputKind: string
{
    case File = 'file';
    case Inline = 'inline';
}
